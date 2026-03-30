<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/activity-logs')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'admin_activity_logs_index')]
    public function index(Request $request, ActivityLogRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $logs = $repo->searchLogs(
            $request->query->get('user'),
            $request->query->all('action'),
            $request->query->get('date_from'),
            $request->query->get('date_to')
        );

        return $this->render('activity_log/index.html.twig', [
            'logs'         => $logs,
            'user_filter'  => $request->query->get('user'),
            'action_filter'=> $request->query->all('action'),
            'date_from'    => $request->query->get('date_from'),
            'date_to'      => $request->query->get('date_to'),
        ]);
    }
}
