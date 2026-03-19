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
