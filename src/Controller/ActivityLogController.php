<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

    /**
     * JSON polling feed — used as fallback when Mercure SSE is unavailable.
     * Returns the most recent N logs so the browser can diff & prepend new ones.
     */
    #[Route('/live', name: 'admin_activity_logs_live', methods: ['GET'])]
    public function live(Request $request, ActivityLogRepository $repo): JsonResponse
    {
        $limit  = max(1, min(200, (int) $request->query->get('limit', 50)));
        $after  = $request->query->get('after'); // ISO-8601 timestamp — return only newer entries

        $logs = $repo->searchLogs(null, [], $after ? $after : null, null);
        $logs = array_slice($logs, 0, $limit);

        $rows = [];
        foreach ($logs as $log) {
            $details = $log->getDetails() ?? '';

            // Rebuild a product list from the details string if it contains "Products: …"
            $products = [];
            if (str_contains($details, 'Products: ')) {
                $productsPart = trim(substr($details, strpos($details, 'Products: ') + 10));
                foreach (explode(', ', $productsPart) as $entry) {
                    $entry = trim($entry);
                    if ($entry !== '') {
                        // Format: "Name ×Qty"
                        if (preg_match('/^(.+)\s×(\d+)$/', $entry, $m)) {
                            $products[] = ['name' => trim($m[1]), 'quantity' => (int) $m[2]];
                        } else {
                            $products[] = ['name' => $entry, 'quantity' => 1];
                        }
                    }
                }
            }

            $rows[] = [
                'id'         => $log->getId(),
                'username'   => $log->getUsername(),
                'userRole'   => $log->getUserRole(),
                'action'     => $log->getAction(),
                'entityType' => $log->getEntityType(),
                'entityId'   => $log->getEntityId(),
                'details'    => $details,
                'products'   => $products,
                'timestamp'  => $log->getTimestamp()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return new JsonResponse(['success' => true, 'logs' => $rows]);
    }
}
