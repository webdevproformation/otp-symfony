# Vidéo sur le sujet

- https://www.youtube.com/watch?v=lkmW57JblzI - YoanDev
cas complet qui part de zéro 
utilise la version 5.4 de Symfony
utilise le module scheb/2fa-bundle => SchebTwoFactorBundle

et il propose une coche appareil de confiance

# Page officielle du module

- <https://symfony.com/bundles/SchebTwoFactorBundle/8.x/index.html>
- [site de demo](https://2fa.scheb.de/2fa)

# Procédure d'installation officielle

- https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html (le module)

# Procédure d'installation module du module pour email : (provider)

composer require scheb/2fa-email

- https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html

---

# ChatGPT réponse  :

Voici un guide pratique pour ajouter 2FA avec SchebTwoFactorBundle en utilisant le provider Email dans un projet Symfony 6.4.x. 

Le bundle est compatible avec Symfony 6, mais les noms exacts de certaines options peuvent légèrement varier selon la version du bundle. 

Vérifiez toujours la doc officielle correspondant à votre version du bundle.

Étapes générales

1) Installer le bundle

- Commande:
  - composer require scheb/two-factor-bundle

2) Activer 2FA sur votre firewall dans security.yaml

- Exemple adapté à votre fichier existant (vous avez déjà un firewall main avec lazy, custom_authenticator, etc.):
- Ajoutez la section two_factor sur le firewall où vous voulez activer la 2FA (ici main).

Extrait à insérer dans votre security.yml (à adapter dans le bloc du firewall main) :

```yml
security:
  # ... votre configuration existante ...

  firewalls:
    dev:
      pattern: ^/(_(profiler|wdt)|css|images|js)/
      security: false

    main:
      lazy: true
      provider: app_users
      custom_authenticator: App\Security\SecurityAuthenticator
      user_checker: App\Security\UserChecker
      logout:
        path: app_logout
      # Activation de la 2FA sur ce firewall
      two_factor:
        check_path: app_two_factor_login        # route du formulaire de code 2FA
        post_only: true
        provider: email                       # on utilise le provider email
      # autres options (remember_me, etc.)
```

Notes:

- check_path: c’est l’URL/route qui affichera le formulaire pour saisir le code 2FA. Vous devrez créer cette route, ou laisser le bundle fournir le formulaire selon la version.
- provider: email indique que l’authentification 2FA utilisera le provider Email (envoi d’un code par email).

3) Configurer SchebTwoFactorBundle (config/scheb_two_factor.yaml)

Créez ou mettez à jour config/packages/scheb_two_factor.yaml avec la configuration du bundle pour le provider Email (et éventuellement totp si vous voulez aussi du TOTPs en plus de l’email).

Exemple de base (Email uniquement, à adapter selon votre version):

```yml
scheb_two_factor:
  # Configuration des "providers" disponibles
  # Ici on active le provider Email
  email:
    from_email: 'no-reply@example.com'        # expéditeur du mail avec le code
    template: '@security/two_factor_email.html.twig'  # template email (à créer)
    mailer: 'mailer'                          # service Mailer utilisé par Symfony
    # optionnels:
    # to_email_field: 'email'                 # champ de l'email utilisateur (si différent)
  
  # Optionnel : totp (Google Authenticator / authenticator TOTP)
  totp:
    issuer: 'YourApp'                          # nom de l'émetteur affiché
    digits: 6
    period: 30
```

Remarques:

- Si vous ne voulez que l’Email, vous pouvez commenter/supprimer la section totp.
- Le template email (security/two_factor_email.html.twig) doit être ajouté dans vos templates (ou adaptez le chemin selon votre organisation des templates).
- Le service mailer utilisé peut être le Mailer de Symfony (mailer) ou tout autre service que vous avez déjà configuré.

4) Mettre à jour l’entité User pour le support 2FA

Le bundle peut fonctionner en mettant en œuvre l’interface TwoFactorInterface (ou une interface spécifique selon la version). L’objectif est de spécifier quel utilisateur est éligible à la 2FA et, si nécessaire, quel provider utiliser pour cet utilisateur.

Exemple conceptuel (à adapter selon la version exacte du bundle):

```php
// src/Entity/User.php
namespace App\Entity;

use Scheb\TwoFactorBundle\Security\TwoFactorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, TwoFactorInterface
{
    // vos propriétés (id, email, etc.)
    private bool $twoFactorEnabled = true;

    // - ... vos méthodes UserInterface ...

    // Méthodes obligatoires par l’interface TwoFactorInterface
    public function isTwoFactorAuthenticationEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function getTwoFactorAuthenticationProviderName(): string
    {
        // nom du provider utilisé pour cet utilisateur (ici 'email')
        return 'email';
    }

    // Si votre bundle nécessite de stocker le temps d’activation, etc.,
    // ajoutez les getters/setters correspondants selon la doc de votre version.
}
```

Important:

- Le nom exact des méthodes et l’interface à implémenter peuvent varier selon la version du bundle. Consultez la doc de SchebTwoFactorBundle correspondant à votre version (v5.x pour Symfony 6 est courant). Dans certains casos, l’interface est appelée TwoFactorInterface et propose des méthodes comme isTwoFactorAuthenticationEnabled() et getTwoFactorAuthenticationProviderName() (ou équivalents). Adaptez les noms en fonction de ce que votre version du bundle attend.

5) Routes et templates

- Le bundle peut générer une route de vérification (par exemple app_two_factor_login). Si vous avez besoin d’un contrôleur personnalisé, vous pouvez en créer un ou utiliser le formulaire par défaut du bundle. Vérifiez la documentation de votre version pour savoir si vous devez ajouter une route manuellement ou si le bundle enregistre les siennes.

6) Vérifications et tests
- Après les modifications, vérifiez la configuration du bundle:
  - bin/console debug:config scheb_two_factor
  - bin/console debug:router (pour voir les routes liées à la 2FA)
- Testez en vous authentifiant et en vérifiant que:
  - Après la connexion initiale, vous êtes redirigé vers la page/étape 2FA (demande du code envoyé par email).
  - Vous recevez l’email avec le code et vous pouvez valider le code pour accéder à l’application.

Conseils et vérifications

- Vérifiez la compatibilité des versions:
  - SchebTwoFactorBundle vX.Y compatible avec Symfony 6.4.20. Si vous utilisez une vieille version du bundle, mettez-la à jour.

- Vérifiez les noms et chemins:
  - check_path doit correspondre à une route existante ou à une route que le bundle peut gérer. Si vous utilisez un custom authenticator, assurez-vous que l’entrée de la 2FA s’intègre bien avec votre flux d’authentification.

- Templates et mails:
  - Créez le template security/two_factor_email.html.twig ou adaptez le chemin dans la config email.template.
  - Assurez-vous que le service mailer (MailerBundle) est correctement configuré dans votre projet.
- Sécurité et tests:
  - Testez d’abord en dev et dans un environnement de test; activez éventuellement un délai pour éviter des envois multiples en cas de test répété.

Exemple de fichier récapitulatif

- security.yaml (extrait)
```yml
security:
  providers:
    app_user_provider:
      entity:
        class: App\Entity\User
        property: email
    app_users:
      chain:
        providers: ['app_user_provider']

  firewalls:
    dev:
      pattern: ^/(_(profiler|wdt)|css|images|js)/
      security: false
    main:
      lazy: true
      provider: app_users
      custom_authenticator: App\Security\SecurityAuthenticator
      user_checker: App\Security\UserChecker
      logout:
        path: app_logout
      two_factor:
        check_path: app_two_factor_login
        post_only: true
        provider: email

  access_control:
    # vos contrôles d'accès
```

- config/packages/scheb_two_factor.yaml

```yml
scheb_two_factor:
  email:
    from_email: 'no-reply@example.com'
    template: '@security/two_factor_email.html.twig'
    mailer: 'mailer'
  totp:
    issuer: 'YourApp'
    digits: 6
    period: 30
```

- src/Entity/User.php (exemple minimal)

```php
namespace App\Entity;

use Scheb\TwoFactorBundle\Security\TwoFactorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, TwoFactorInterface
{
    // vos propriétés (id, email, etc.)

    private bool $twoFactorEnabled = true;

    public function isTwoFactorAuthenticationEnabled(): bool
    {
        return $this->twoFactorEnabled;
    }

    public function getTwoFactorAuthenticationProviderName(): string
    {
        return 'email';
    }

    // implémentez les autres méthodes de UserInterface
}
```

Si vous préférez, je peux vous fournir une version prête-à-copier avec les noms de méthodes exacts adaptés à la version précise de SchebTwoFactorBundle que vous utilisez. Dites-moi la version du bundle (par ex. scheb/two-factor-bundle v6.x ou v5.x) et je vous donne les morceaux exacts (config et méthodes d’interface) correspondants.

---

## réponse Copilot


# Symfony 6.4.20 — Ajouter 2FA/MFA **par email** avec **SchebTwoFactorBundle** (provider email)

Ce guide se base sur la doc officielle de SchebTwoFactorBundle et du provider **Code-via-Email**. 

- [1](https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html)
- [2](https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html)

---

## Installer les dépendances

```sh
composer require scheb/2fa-bundle scheb/2fa-email
```

Le provider email “code-via-email” nécessite un système d’envoi d’email ; l’implémentation par défaut suppose symfony/mailer. [symfony.com]

```sh
composer require symfony/mailer
```

## Activer le bundle (si Symfony Flex ne l’a pas fait)

Dans config/bundles.php :

```php
return [
    // ...
    Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],
];
```

## Ajouter les routes 2FA (obligatoires)

Créer config/routes/scheb_2fa.yaml (ou vérifier qu’il existe) :

```yml
# config/routes/scheb_2fa.yaml
2fa_login:
  path: /2fa
  controller: "scheb_two_factor.form_controller::form"

2fa_login_check:
  path: /2fa_check
```


## Configurer le provider email

Créer config/packages/scheb_2fa.yaml :

```yml
# config/packages/scheb_2fa.yaml
scheb_two_factor:
  email:
    enabled: true
    digits: 6
    sender_email: "no-reply@example.com"
    sender_name: "Mon Application" # optionnel
```

Le provider email génère un code numérique, le persiste sur l’utilisateur, l’envoie par email puis l’utilisateur doit saisir ce code.

[symfony.com]<https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html>

Le nombre de digits est configurable (digits). [symfony.com]<https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html>

Le provider peut être configuré avec un mailer custom, un code_generator custom, etc. [symfony.com], [symfony.com]<https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html>

5) Adapter ton User (obligatoire)

Ton entité App\Entity\User doit implémenter l’interface email :

Scheb\TwoFactorBundle\Model\Email\TwoFactorInterface [symfony.com]
Et stocker le code 2FA dans un champ persisté (ex : authCode nullable). [symfony.com]
Exemple (logique attendue par la doc) : [symfony.com]

isEmailAuthEnabled(): bool → active/désactive la 2FA email (peut être un champ persisté)
getEmailAuthRecipient(): string → l’adresse email destinataire
getEmailAuthCode() / setEmailAuthCode() → getter/setter du code persisté


⚠️ Point clé : “The authentication code must be persisted” (donc champ Doctrine). [symfony.com]


6) Modifier ton security.yaml (TON fichier)

Tu ajoutes seulement :

- la section two_factor dans le firewall main
- access_control pour autoriser /2fa uniquement quand la 2FA est en cours, et rendre logout accessible.

La doc d’installation indique :

- Ajouter two_factor: auth_form_path: 2fa_login et check_path: 2fa_login_check au firewall. [symfony.com.cn]
- Mettre les règles access_control en tout premier (important). [symfony.com.cn]
- Rendre le logout accessible pendant la 2FA avec PUBLIC_ACCESS. [symfony.com.cn], [symfony.com]

✅ Version modifiée de ton security.yaml (extrait complet)

```yml
security:
  password_hashers:
    Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

  providers:
    app_user_provider:
      entity:
        class: App\Entity\User
        property: email

    app_users:
      chain:
        providers: ['app_user_provider']

  role_hierarchy:
    ROLE_USER: ROLE_USER
    ROLE_COMMERCIAL: [ROLE_COMMERCIAL, ROLE_USER]
    ROLE_ENTREPRISE: [ROLE_ENTREPRISE, ROLE_USER]
    ROLE_PEDAGOGIE: [ROLE_PEDAGOGIE, ROLE_USER]
    ROLE_COMPTABLE: [ROLE_COMPTABLE, ROLE_USER]
    ROLE_ADMIN: [ROLE_COMMERCIAL, ROLE_ENTREPRISE, ROLE_USER, ROLE_COMPTABLE, ROLE_PEDAGOGIE]

  firewalls:
    dev:
      pattern: ^/(_(profiler|wdt)|css|images|js)/
      security: false

    main:
      lazy: true
      provider: app_users
      custom_authenticator: App\Security\SecurityAuthenticator
      user_checker: App\Security\UserChecker

      # ✅ AJOUT 2FA (routes définies dans config/routes/scheb_2fa.yaml)
      two_factor:
        auth_form_path: 2fa_login
        check_path: 2fa_login_check
        # enable_csrf: true  # optionnel si tu actives la protection CSRF sur le form 2FA

      logout:
        path: app_logout


  # ✅ IMPORTANT : mettre ces règles TOUT EN HAUT de access_control [3](https://symfony.com.cn/bundles/SchebTwoFactorBundle/current/installation.html)
  access_control:
    # rendre le logout accessible pendant la 2FA (sinon boucles / redirections)
    - { path: ^/logout, roles: PUBLIC_ACCESS }               # [3](https://symfony.com.cn/bundles/SchebTwoFactorBundle/current/installation.html)[5](https://symfony.com/bundles/SchebTwoFactorBundle/8.x/troubleshooting.html)

    # routes 2FA accessibles uniquement quand la 2FA est en cours
    - { path: ^/2fa, roles: IS_AUTHENTICATED_2FA_IN_PROGRESS }  # [3](https://symfony.com.cn/bundles/SchebTwoFactorBundle/current/installation.html)

    # (ajoute ici tes règles publiques si besoin, puis tes règles ROLE_USER/ROLE_ADMIN)

when@test:
  security:
    password_hashers:
      Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
        algorithm: auto
        cost: 4
        time_cost: 3
        memory_cost: 10
```

Note : la règle /logout ci-dessus doit correspondre à TON chemin effectif de logout.
La doc troubleshooting explique que si logout n’est pas autorisé par access_control,
le logout peut rediriger vers le formulaire 2FA (comportement typique). [symfony.com]


7) Ce que tu dois vérifier après intégration

- `scheb_2fa.yaml` : email enabled + sender_email + digits. [symfony.com]<https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html>
- `routes/scheb_2fa.yaml` : /2fa et /2fa_check existent. [symfony.com], [symfony.com.cn]
- `security.yaml` : two_factor sur le firewall main + access_control en haut. [symfony.com.cn]
- `User` : implémente Email\TwoFactorInterface + champ persisté pour le code. [symfony.com]<https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html>


Liens doc utiles

Installation : 

- <https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html> [symfony.com]
Provider email : 
- <https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html> [symfony.com]
Référence config : 

- <https://symfony.com/bundles/SchebTwoFactorBundle/current/configuration.html> [symfony.com] 
- <https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html>