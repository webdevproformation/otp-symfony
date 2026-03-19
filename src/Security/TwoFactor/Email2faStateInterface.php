<?php 

namespace App\Security\TwoFactor;

interface Email2faStateInterface
{
    public function setEmailAuthCodeExpiresAt(\DateTimeImmutable $expiresAt): static;
    public function setEmailAuthCodeLastSentAt(\DateTimeImmutable $sentAt): static;
}
