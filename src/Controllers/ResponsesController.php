<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthGuard;
use App\Core\Request;
use App\Core\Response;
use App\Models\FormRepository;
use App\Models\ResponseRepository;
use App\Services\Exporter;
use App\Services\StatsService;

class ResponsesController extends Controller
{
    public function index(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $formId = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($formId);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canView($form)) return Response::text('Forbidden', 403);

        /** @var ResponseRepository $rr */
        $rr = $this->c->get('responseRepo');
        $page = max(1, (int)$r->query('page', 1));
        $sort = (string)$r->query('sort', 'submitted_at');
        $dir  = (string)$r->query('dir', 'DESC');
        $list = $rr->paginate($formId, $page, 20, $sort, $dir);

        return $this->render('responses/list', [
            'pageTitle' => $this->c->get('translator')->t('resp.list.title'),
            'form'      => $form,
            'rows'      => $list['rows'],
            'total'     => $list['total'],
            'page'      => $page,
            'perPage'   => 20,
            'sort'      => $sort,
            'dir'       => $dir,
        ]);
    }

    public function show(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $formId = (int)$r->params['id'];
        $rid    = (int)$r->params['rid'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($formId);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canView($form)) return Response::text('Forbidden', 403);
        /** @var ResponseRepository $rr */
        $rr = $this->c->get('responseRepo');
        $resp = $rr->find($rid);
        if (!$resp || (int)$resp['form_id'] !== $formId) return Response::text('Not Found', 404);
        return $this->render('responses/show', [
            'pageTitle' => $this->c->get('translator')->t('common.view'),
            'form'      => $form,
            'response'  => $resp,
        ]);
    }

    public function delete(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $formId = (int)$r->params['id'];
        $rid    = (int)$r->params['rid'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($formId);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canView($form)) return Response::text('Forbidden', 403);
        /** @var ResponseRepository $rr */
        $rr = $this->c->get('responseRepo');
        $rr->delete($rid);
        return $this->redirect('/forms/' . $formId . '/responses');
    }

    public function stats(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $formId = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($formId);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canView($form)) return Response::text('Forbidden', 403);
        /** @var StatsService $svc */
        $svc = $this->c->get('stats');
        $aggregates = $svc->aggregate($formId);
        return $this->render('responses/stats', [
            'pageTitle' => $this->c->get('translator')->t('resp.stats'),
            'form'      => $form,
            'aggregates'=> $aggregates,
        ]);
    }

    public function exportCsv(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $formId = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($formId);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canView($form)) return Response::text('Forbidden', 403);

        // 直接輸出 (不走 Response 框架，以串流避免大量資料)
        $filename = 'form_' . $formId . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        /** @var Exporter $exp */
        $exp = $this->c->get('exporter');
        $exp->streamCsv($formId);
        // 已輸出，回空 Response 防止 layout
        $resp = new Response();
        $resp->status = 200;
        return $resp;
    }

    private function canView(array $form): bool
    {
        if (AuthGuard::isAdmin()) return true;
        return (int)$form['created_by'] === (int)AuthGuard::id();
    }
}
