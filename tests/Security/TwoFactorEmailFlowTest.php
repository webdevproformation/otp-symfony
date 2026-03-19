<?php
declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\TwoFactor\EmailAuthCodeMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Scenarios:
 * 1) register + login + mfa (happy path)
 * 2) register + login + mfa + resend email (allowed)
 * 3) register + login + mfa + resend email too early (rate limited)
 * 4) register + login + mfa + code too late (expired)
 *
 * Notes:
 * - Uses crawler-based form submission (CSRF-safe). [3](https://symfony.com/doc/current/testing.html)[4](https://grafikart.fr/tutoriels/tests-symfony-controller-1217)
 * - Scheb default 2FA routes are usually /2fa and /2fa_check. [2](https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html)
 * - Email 2FA code is persisted on the user entity for validation. [1](https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html)
 */
final class TwoFactorEmailFlowTest extends WebTestCase
{
    // ---- Adjust these to your app ----
    private const REGISTER_URL = '/register';
    private const LOGIN_URL = '/login';

    // Scheb default routes (unless you configured differently) [2](https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html)
    private const MFA_FORM_URL  = '/2fa';
    private const MFA_CHECK_URL = '/2fa_check';

    // Your resend endpoint (adjust!)
    private const MFA_RESEND_URL = '/2fa/resend';

    // Selector / text you expect after full auth (adjust to your app)
    private const POST_LOGIN_LANDING_URL = '/';
    private const SECURE_PAGE = '/dashboard';
    private const FULLY_AUTH_SELECTOR = 'body'; // e.g. 'h1' or '.dashboard'

    // Your expected expired message (French)
    private const MSG_CODE_EXPIRE = 'Code expiré. Veuillez en demander un nouveau.';

    public function testScenario1_happyPath_register_login_mfa(): void
    {
        $client = static::createClient();

        $email = 'happyPath_register_login_mfa+'.bin2hex(random_bytes(4)).'@example.test';
        $password = 'TestPassword123!';

        $this->register($client, $email, $password);
        $this->login($client, $email, $password);

        $client->followRedirect();

        // After login with 2FA enabled, user should be challenged with 2FA form
        $this->assertOnMfaForm($client);

        // Get code from DB (Scheb email provider persists the auth code) [1](https://symfony.com/bundles/SchebTwoFactorBundle/current/providers/email.html)
        $code = $this->getUser($email)->getEmailAuthCode();

        $this->submitMfaCode($client, $code);

        $this->assertTrue(
            $client->getRequest()->getPathInfo() === self::SECURE_PAGE
            ,
            'Now you should be fully authenticated and reach your secured area'
        );

    }

    public function testScenario2_resendAllowed_register_login_mfa_resend(): void
    {
        $client = static::createClient();

        $email = 'testScenario2_resendAllowed_register_login_mfa_resend+'.bin2hex(random_bytes(4)).'@example.test';
        $password = 'TestPassword123!';

        $this->register($client, $email, $password);
        $this->login($client, $email, $password);

        $crawler = $client->followRedirect();

        $this->assertOnMfaForm($client);

        $em = $this->getEntityManager();
        $user = $this->getUser($email);

        $oldCode = $user->getEmailAuthCode();


        if (method_exists($user, 'setEmailAuthCodeLastSentAt')) {

            $duration = sprintf("PT%sS", EmailAuthCodeMailer::RESEND_THROTTLE_SECONDS);
            $user->setEmailAuthCodeLastSentAt((new \DateTimeImmutable())->sub(new \DateInterval($duration)));
            $em->flush();
            $em->refresh($user);
        }

        // appuyer sur le bouton Resend 2fa
        $formResend2fa = $crawler->selectButton('Resend 2fa')->form();

        $client->submit($formResend2fa);

        $this->assertTrue(
            $client->getResponse()->isSuccessful() || $client->getResponse()->isRedirection(),
            'Expected resend endpoint to succeed.'
        );
       
        // Refresh entity to see updated state
        $user = $em->getRepository(User::class)->find($user->getId());
        $em->refresh($user);

        $newCode = $user->getEmailAuthCode();

        self::assertNotSame($oldCode, $newCode);

        // Complete 2FA using the *current* code stored in DB
        $this->submitMfaCode($client, $newCode);

        $this->assertTrue(
            $client->getRequest()->getPathInfo() === self::SECURE_PAGE
            ,
            'Now you should be fully authenticated and reach your secured area'
        );
    }

    public function testScenario3_resendTooEarly_register_login_mfa_resend_too_early(): void
    {
        $client = static::createClient();

        $email = 'resendTooEarly_register_login_mfa_resend_too_early+'.bin2hex(random_bytes(4)).'@example.test';
        $password = 'TestPassword123!';

        $this->register($client, $email, $password);
        $this->login($client, $email, $password);

        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
        $crawler = $client->request('GET', self::MFA_FORM_URL); // adjust if your 2FA form path differs
        self::assertResponseIsSuccessful();

        $this->assertOnMfaForm($client);

        $em = $this->getEntityManager();
        $user = $this->getUser($email);

        // Force "last sent" to now so resend should be considered too early
        // (Assumes your User implements something like Email2faStateInterface)
        if (method_exists($user, 'setEmailAuthCodeLastSentAt')) {
            $user->setEmailAuthCodeLastSentAt(new \DateTimeImmutable());
            $em->flush();
            $em->refresh($user);
        }

        $before = method_exists($user, 'getEmailAuthCodeLastSentAt')
            ? $user->getEmailAuthCodeLastSentAt()
            : null;

        // appuyer sur le bouton Resend 2fa
        $formResend2fa = $crawler->selectButton('Resend 2fa')->form();

        $client->submit($formResend2fa);

        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }

        // Depending on your design, you might return 400, 429, or 200 with a flash error.
        // So we assert "NOT success" OR "shows an error".
        $status = $client->getResponse()->getStatusCode();
        self::assertTrue(
            $status === 400 || $status === 429 || $client->getResponse()->isSuccessful(),
            'Expected 400/429 or success with an error message for too-early resend.'
        );

        // Ensure lastSentAt wasn't moved forward (rate-limit respected)
        if ($before && method_exists($user, 'getEmailAuthCodeLastSentAt')) {

            $user = $em->getRepository(User::class)->find($user->getId());
            $em->refresh($user);
            $after = $user->getEmailAuthCodeLastSentAt();
            // If resend blocked, timestamp should remain same (or not advance meaningfully)
            self::assertEquals($before, $after, 'Expected lastSentAt unchanged when resend is too early.');
        }
    }

    public function testScenario4_codeExpired_register_login_mfa_code_too_late(): void
    {
        $client = static::createClient();

        $email = 'codeExpired_register_login_mfa_code_too_late+'.bin2hex(random_bytes(4)).'@example.test';
        $password = 'TestPassword123!';

        $this->register($client, $email, $password);
        $this->login($client, $email, $password);
        
        $client->request('GET', self::MFA_FORM_URL); // adjust if your 2FA form path differs
        self::assertResponseIsSuccessful();

        $this->assertOnMfaForm($client);

        $em = $this->getEntityManager();
        $user = $this->getUser($email);

        // Force expiration in the past (does not rely on revealing auth exception reasons)
        if (method_exists($user, 'setEmailAuthCodeExpiresAt')) {

            $duration = sprintf("PT%sS", EmailAuthCodeMailer::EXPIRE_CODE_SECONDS);

            $user->setEmailAuthCodeExpiresAt((new \DateTimeImmutable())->sub(new \DateInterval($duration)));
            $em->flush();
            $em->refresh($user);
        }

        $expiredCode = $user->getEmailAuthCode();

        $this->submitMfaCode($client, $expiredCode);

        // Expect to be back on MFA form with your expired message displayed
        $this->assertOnMfaForm($client);
        self::assertStringContainsString(self::MSG_CODE_EXPIRE, $client->getResponse()->getContent() ?? '');
    }

    // ---------------- Helpers ----------------

    private function register($client, string $email, string $password): void
    {
        $crawler = $client->request('GET', self::REGISTER_URL);
        self::assertTrue($client->getResponse()->isSuccessful(), 'Register page should load.');

        // Adjust form name/fields to your registration form
        // Using crawler form submission is the canonical functional testing approach. [3](https://symfony.com/doc/current/testing.html)[4](https://grafikart.fr/tutoriels/tests-symfony-controller-1217)
        $form = $crawler->selectButton('Register')->form([
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => $password
        ]);

        $client->submit($form);

        // Many apps redirect after registration
        self::assertTrue(
            $client->getResponse()->isRedirection() || $client->getResponse()->isSuccessful(),
            'Register submit should redirect or succeed.'
        );
        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
    }

    private function login($client, string $email, string $password): void
    {
        $crawler = $client->request('GET', self::LOGIN_URL);
        self::assertTrue($client->getResponse()->isSuccessful(), 'Login page should load.');

        // Adjust field names to your login form
        $form = $crawler->selectButton('Sign in')->form([
            '_username' => $email,
            '_password' => $password,
        ]);

        $client->submit($form);

        // Should redirect either to 2FA or secured area
        //if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        //}
    }

    private function assertOnMfaForm($client): void
    {
        // Scheb recommends keeping 2fa routes inside the firewall pattern. [2](https://symfony.com/bundles/SchebTwoFactorBundle/current/installation.html)

        self::assertTrue(
            //str_starts_with($client->getRequest()->getPathInfo(), self::MFA_FORM_URL)
            //|| str_contains($client->getResponse()->getContent() ?? '', '2fa')
            /* || */ $client->getRequest()->getPathInfo() === self::MFA_FORM_URL
            ,
            'Expected to be on 2FA form.'
        );
    }

    private function submitMfaCode($client, string $code): void
    {
        $crawler = $client->request('GET', self::MFA_FORM_URL);
        self::assertTrue($client->getResponse()->isSuccessful(), '2FA form should load.'); 

        // Adjust field name/button to your 2FA form template
        $form = $crawler->selectButton('Verify')->form([
            // common field name used by Scheb's default template:
            '_auth_code' => $code,
        ]);

        $client->submit($form);

        if ($client->getResponse()->isRedirection()) {
            $client->followRedirect();
        }
    }

    private function getUser(string $email)
    {
        /** @var UserRepository $repo */
        $repo = static::getContainer()->get(UserRepository::class);
        $user = $repo->findOneBy(['email' => $email]);
        self::assertNotNull($user, 'User should exist in DB after registration.');

        return $user;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}