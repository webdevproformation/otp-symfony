# installation 

```sh
symfony new 2fa --version="6.4.*" --webapp
```

# base de données 

- créer un fichier .env.local 
- créer la base de données  `symfony console d:d:c`

```sql
SELECT VERSION();
-- 10.6.5-MariaDB
-- modifier le fichier .env.local 
```

# un controller

- `symfony console make:controller AdminController`

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute("app_admin") ;
    }

    #[Route('/admin', name: 'app_admin')]
    public function admin(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

}
``` 

# `security.yaml`



# start serveur de développement

```sh
symfony serve
```

# Préparation

## créer la table user

```sh
symfony console make:user


# created: src/Entity/User.php
# created: src/Repository/UserRepository.php
# updated: src/Entity/User.php
# updated: config/packages/security.yaml

symfony console make:migration
symfony console d:m:m
```

## Formulaire de connexion

```sh
# déprécié
symfony console make:auth
# nouvelle commande
symfony console make:security:form-login 

# created: src/Controller/SecurityController.php
# created: templates/security/login.html.twig
# updated: config/packages/security.yaml
```

## Formulaire d'inscription (pour ajouter un user)

```sh
symfony console make:registration-form

# updated: src/Entity/User.php
# created: src/Form/RegistrationFormType.php
# created: src/Controller/RegistrationController.php
# created: templates/registration/register.html.twig
```

![](img/registration.png)

<https://127.0.0.1:8000/register>

toto@yahoo.fr


# 2fa

## Page officielle du module

- <https://symfony.com/bundles/SchebTwoFactorBundle/8.x/index.html>
- [site de demo](https://2fa.scheb.de/2fa)


```sh
composer require 2fa
composer require scheb/2fa-email
```

création de deux fichiers yml 

- config/packages/scheb_2fa.yaml
- config/routes/scheb_2fa.yaml


modification du fichier `config/packages/security.yaml`

```yml
firewalls:
    dev:
        pattern: ^/(_(profiler|wdt)|css|images|js)/
        security: false
    main:
        lazy: true
        provider: app_user_provider
        form_login:
            login_path: app_login
            check_path: app_login
            enable_csrf: true
        logout:
            path: app_logout
            # where to redirect after logout
            # target: app_any_route

        # Activation de la 2FA sur ce firewall
        two_factor:
            auth_form_path: 2fa_login   # route du formulaire de 2fa
            check_path: 2fa_login_check # route pour vérifier ce 2ème élément de sécurité
```

modification du fichier `config/packages/scheb_2fa.yaml`

```yml
# See the configuration reference at https://symfony.com/bundles/SchebTwoFactorBundle/6.x/configuration.html
scheb_two_factor:
    security_tokens:
        - Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken
        - Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken
    email:
        digits: 6
        enabled: true
        sender_email: toto@test.fr
        sender_name: Toto
```

## modifier du l'entité User

implémente `TwoFactorInterface`
```php
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface

{

    /**
     * @var string 
     */
    #[ORM\Column(nullable:true)]
    private ?string $authCode = null;


    // ....

     /**
     * Return true if the user should do two-factor authentication.
     * active/désactive la 2FA email (peut être un champ persisté)
     */
    public function isEmailAuthEnabled(): bool
    {
        return true ;
    }

    /**
     * Return user email address.
     *  l’adresse email destinataire
     */
    public function getEmailAuthRecipient(): string
    {
        return $this->email ;
    }

    /**
     * Return the authentication code.
     * getter/setter du code persisté
     */
    public function getEmailAuthCode(): string|null
    {
        if(null === $this->authCode){
            throw new \LogicException("The email authentification code was not set");
        }
        return $this->authCode ;
    }

    /**
     * Set the authentication code.
     */
    public function setEmailAuthCode(string $authCode): void
    {
        $this->authCode = $authCode ; 
    }
}
```


```sh
symfony console make:migration
symfony console d:m:m
```

toto@yahoo.fr


## serveur email set up

