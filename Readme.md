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

```sh
symfony console make:controller AdminController
```

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{

    #[Route('/', name: 'app_index')]
    public function index(): Response
    {
        return $this->render('admin/home.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/dashboard', name: 'app_admin')]
    public function admin(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

}
```

<http://localhost:8000>
<http://localhost:8000/dashboard>

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

<https://127.0.0.1:8000/login>

## Formulaire d'inscription (pour ajouter un user)

```sh
symfony console make:registration-form

# updated: src/Entity/User.php
# created: src/Form/RegistrationFormType.php
# created: src/Controller/RegistrationController.php
# created: templates/registration/register.html.twig
```

<https://127.0.0.1:8000/register>



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
security:
    # https://symfony.com/doc/current/security.html#registering-the-user-hashing-passwords
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
    # https://symfony.com/doc/current/security.html#loading-the-user-the-user-provider
    providers:
        # used to reload user from session & other features (e.g. switch_user)
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email
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
                success_handler: App\Security\TwoFactor\TwoFactorSuccessHandler
            logout:
                path: app_logout
                # where to redirect after logout
                target: app_login

            # Activation de la 2FA sur ce firewall
            two_factor:
                auth_form_path: 2fa_login   # route du formulaire de 2fa
                check_path: 2fa_login_check # another route for checking the two-factor authentication code
                post_only: true
                enable_csrf: true
                csrf_token_id: authenticate_2fa
            

            # activate different ways to authenticate
            # https://symfony.com/doc/current/security.html#the-firewall

            # https://symfony.com/doc/current/security/impersonating_user.html
            # switch_user: true

    # Easy way to control access for large sections of your site
    # Note: Only the *first* access control that matches will be used
    access_control:
        # - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/logout, roles: PUBLIC_ACCESS }
        - { path: ^/2fa, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }
        - { path: ^/2fa/resend, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }
        - { path: ^/admin, roles: ROLE_USER }
```

modification du fichier `config/packages/scheb_2fa.yaml`

```yml
# See the configuration reference at https://symfony.com/bundles/SchebTwoFactorBundle/6.x/configuration.html
scheb_two_factor:
    security_tokens:
        - Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken
        - Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken
    email:
        enabled: true
        mailer: App\Security\TwoFactor\EmailAuthCodeMailer
        sender_email: toto@yahoo.fr
        sender_name: Toto
        digits: 6
        template: security/2fa_form.html.twig
```

## modifier l'entité User

implémente `TwoFactorInterface` et `Email2faStateInterface`

```php
class User implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface, Email2faStateInterface
{

    /**
     * @var string 
     */
    #[ORM\Column(nullable:true)]
    private ?bool $enable2FA = null;


    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailAuthCodeExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailAuthCodeLastSentAt = null;

    // ...

    /* 2FA */

    /**
     * Return true if the user should do two-factor authentication.
     */
    public function isEmailAuthEnabled(): bool
    {
        return (bool)$this->enable2FA ;
    }

    public function setEmailAuthEnabled(?bool $enable2FA): static
    {
        $this->enable2FA = $enable2FA;
        return $this;
    }

    /**
     * Return user email address.
     */
    public function getEmailAuthRecipient(): string
    {
        return $this->email ;
    }

    /**
     * Return the authentication code.
     */
    public function getEmailAuthCode(): string|null
    {
        if(null === $this->authCode){
            throw new \LogicException("The email authentification code waw not set");
        }
        return $this->authCode ;
    }

    /**
     * Set the authentication code.
     */
    public function setEmailAuthCode(?string $authCode): void
    {
        $this->authCode = $authCode ; 
    }


    public function getEmailAuthCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->emailAuthCodeExpiresAt;
    }

    public function setEmailAuthCodeExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->emailAuthCodeExpiresAt = $expiresAt;
        return $this;
    }

    public function getEmailAuthCodeLastSentAt(): ?\DateTimeImmutable
    {
        return $this->emailAuthCodeLastSentAt;
    }

    public function setEmailAuthCodeLastSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->emailAuthCodeLastSentAt = $sentAt;
        return $this;
    }
}
```


```php
<?php 

namespace App\Security\TwoFactor;

interface Email2faStateInterface
{
    public function setEmailAuthCodeExpiresAt(\DateTimeImmutable $expiresAt): static;
    public function setEmailAuthCodeLastSentAt(\DateTimeImmutable $sentAt): static;
}
```


```sh
symfony console make:migration
symfony console d:m:m
```

## Service en charge de l'émission de l'email

```php
<?php
declare(strict_types=1);

// src/Security/TwoFactor/EmailAuthCodeMailer.php
namespace App\Security\TwoFactor;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Mailer\AuthCodeMailerInterface;
use Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailAuthCodeMailer implements AuthCodeMailerInterface
{

    /** 10 minutes = 60 * 10 
    * TTL - Time To Live - indique le temps pendant lequel une information doit être conservée
    **/
    public const EXPIRE_CODE_SECONDS = 600 ;

    // If you implemented resend throttling, set this to your throttle (seconds)
    // Cooldown = délai de délai de récupération
    public const RESEND_THROTTLE_SECONDS = 30;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
        private readonly string $senderEmail = "toto@yahoo.fr",
        private readonly string $senderName = "TOTO"
    ) {}

    public function sendAuthCode(TwoFactorInterface $user): void
    {
        // "now" unique et testable (Clock)

        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        $expiresAt = $now->modify(sprintf('+%d seconds', self::EXPIRE_CODE_SECONDS));

        $recipient = (string) $user->getEmailAuthRecipient();
        if ($recipient === '') {
            // Fail fast: missing email destination is a config/user-data error.
            $this->logger->error('2FA email recipient is empty', [
                'userClass' => $user::class,
            ]);
            throw new \InvalidArgumentException('2FA email recipient is empty.');
        }

        if ($user instanceof Email2faStateInterface) {
            $user->setEmailAuthCodeLastSentAt($now);
            $user->setEmailAuthCodeExpiresAt($expiresAt);
        }


        $email = (new TemplatedEmail())
        ->from(new Address($this->senderEmail, $this->senderName))
        ->to(new Address($user->getEmailAuthRecipient()))
        ->subject('Vérification de votre identité')
        ->htmlTemplate('security/2fa_email.html.twig')
        ->context([
            'emailDestinataire' => $recipient,
            'code'              => $user->getEmailAuthCode(),
            'sentAt'            => $now,
            'expiresAt'         => $expiresAt,
            'ttl'               => self::EXPIRE_CODE_SECONDS
        ]);

        try{

            $this->mailer->send($email);
            $this->em->flush();

        }catch(TransportExceptionInterface $e)
        {
            $this->logger->error('Failed to send 2FA email code', [
                'recipient' => $user->getEmailAuthRecipient(),
                'error' => $e->getMessage(),
            ]);
            // Remonte l’erreur (ou transforme en exception métier)
            throw $e;
        }catch (\Throwable $e) {
            $this->logger->error('Failed to send 2FA email (unexpected)', [
                'recipient' => $recipient,
                'exception' => $e::class,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
```


---

## serveur email set up

<http://localhost:1080>

```docker
services:
  mailcatcher:
    image: dockage/mailcatcher:0.7.1
    container_name: mailcatcher_2fa
    ports:
        - 1080:1080
        - 1025:1025

```

## Redirection en cas de succes

```php
<?php
namespace App\Security\TwoFactor;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class TwoFactorSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RouterInterface $router) {}

    public function onAuthenticationSuccess(
        Request $request, 
        TokenInterface $token
        ): ?Response
    {
        return new RedirectResponse(
            $this->router->generate('app_admin')
        );
    }
}
```

## resend email

```php
<?php
namespace App\Controller;

use App\Entity\User;
use App\Security\TwoFactor\EmailAuthCodeMailer;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Email\Generator\CodeGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


final class Auth2FaController extends AbstractController
{

    #[Route('/2fa/resend', name: 'app_resend_code_2fa' , methods:["POST"])]
    public function resend(
        CodeGeneratorInterface $baseGenerator,
        Request $request
    ): Response {

        $submittedToken = $request->getPayload()->get('token');

        // 'delete-item' is the same value used in the template to generate the token
        if (!$this->isCsrfTokenValid('resend-2fa', $submittedToken)) {
            $this->addFlash('warning', 'CSRF invalide');
            return $this->redirectToRoute('2fa_login');
        }

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        //  Cooldown = délai de délai de récupération
        $duration = sprintf("PT%sS", EmailAuthCodeMailer::RESEND_THROTTLE_SECONDS);
        if (
            $user->getEmailAuthCodeLastSentAt()
            && $user->getEmailAuthCodeLastSentAt() > (new \DateTimeImmutable())->sub(new \DateInterval($duration))
        ) {
            $this->addFlash('warning', 'Veuillez patienter avant de renvoyer le code.');
            return $this->redirectToRoute('2fa_login');
        }

        // ça va appeler 
        // App\Security\TwoFactor\EmailAuthCodeMailer::sendAuthCode
        $baseGenerator->generateAndSend($user);

        $this->addFlash('success', 'Un nouveau code a été envoyé.');
        return $this->redirectToRoute('2fa_login');
    }
}
```

# Events : verifier que le code n'a pas expiré + vider le code suite à la connexion

```php
<?php
// src/EventSubscriber/Email2FAValidationSubscriber.php

namespace App\EventSubscriber;

// use Scheb\TwoFactorBundle\Event\TwoFactorAuthenticationEvent;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class Email2FAValidationSubscriber implements EventSubscriberInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function getSubscribedEvents(): array
    {
        return [
            TwoFactorAuthenticationEvents::ATTEMPT => 'on2faCheck',
            TwoFactorAuthenticationEvents::COMPLETE => 'onTwoFactorComplete',
        ];
    }

    public function on2faCheck(TwoFactorAuthenticationEvent $event): void
    {
        /** @var User $user */
        $user = $event->getToken()->getUser();

        if (
            $user->getEmailAuthCodeExpiresAt() !== null
            && $user->getEmailAuthCodeExpiresAt() < new \DateTimeImmutable()
        ) {
            throw new AuthenticationException('Code expiré. Veuillez en demander un nouveau.');
        }
    }
    
    public function onTwoFactorComplete(TwoFactorAuthenticationEvent $event): void
    {
        /** @var User $user */
        $user = $event->getToken()->getUser();

        // Example: if you store the email code on the User entity:
        if (method_exists($user, 'setEmailAuthCode')) {
            $user->setEmailAuthCode(null);
        }
        if (method_exists($user, 'setEmailAuthCodeExpiresAt')) {
            $user->setEmailAuthCodeExpiresAt(null);
        }
        if (method_exists($user, 'setEmailAuthCodeLastSentAt')) {
            $user->setEmailAuthCodeLastSentAt(null);
        }

        $this->em->flush();
    }

}
```
