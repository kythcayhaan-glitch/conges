<?php

declare(strict_types=1);

namespace App\Web\Controller;

use App\Leave\Domain\Repository\LeaveRequestRepositoryInterface;
use App\Security\Entity\User;
use App\Shared\Domain\ValueObject\LeaveBalance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agents', name: 'app_agents_')]
final class AgentWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface          $em,
        private readonly LeaveRequestRepositoryInterface $leaveRepository,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function list(): Response
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['username' => 'ASC']);

        return $this->render('agent/list.html.twig', ['users' => $users]);
    }

    #[Route('/profile', name: 'profile', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function profile(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->redirectToRoute('app_agents_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('ROLE_AGENT')]
    public function show(string $id): Response
    {
        $currentUser = $this->getUser();

        if (!$this->isGranted('ROLE_RH')) {
            if (!$currentUser instanceof User || $currentUser->getId() !== $id) {
                throw $this->createAccessDeniedException();
            }
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        return $this->render('agent/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/balance', name: 'balance', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function updateBalance(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('balance_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_agents_show', ['id' => $id]);
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $congeBalance    = (float) $request->request->get('congeBalance', $user->getLeaveBalance()->getValue());
        $rttBalance      = (float) $request->request->get('rttBalance', $user->getRttBalance()->getValue());
        $heureSupBalance = (float) $request->request->get('heureSupBalance', $user->getHeureSupBalance()->getValue());

        if ($congeBalance < 0 || $rttBalance < 0 || $heureSupBalance < 0) {
            $this->addFlash('error', 'Les soldes ne peuvent pas être négatifs.');
            return $this->redirectToRoute('app_agents_show', ['id' => $id]);
        }

        $user->applyLeaveBalance(new LeaveBalance($congeBalance));
        $user->applyRttBalance(new LeaveBalance($rttBalance));
        $user->applyHeureSupBalance(new LeaveBalance($heureSupBalance));
        $this->em->flush();

        $this->addFlash('success', 'Soldes mis à jour.');

        return $this->redirectToRoute('app_agents_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('agent_delete_' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('app_agents_show', ['id' => $id]);
        }

        $user = $this->em->getRepository(User::class)->find($id);

        if ($user === null) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $name = $user->getNomComplet() ?? $user->getUsername();

        $this->leaveRepository->deleteAllByUserId($id);

        $this->em->remove($user);
        $this->em->flush();

        $this->addFlash('success', sprintf(
            'L\'utilisateur %s a été supprimé ainsi que toutes ses demandes de congé.',
            $name
        ));

        return $this->redirectToRoute('app_agents_list');
    }
}
