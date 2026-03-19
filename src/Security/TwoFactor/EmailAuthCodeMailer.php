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