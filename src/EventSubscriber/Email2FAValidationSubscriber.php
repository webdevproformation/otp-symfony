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