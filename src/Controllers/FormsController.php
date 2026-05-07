<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthGuard;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\FormRepository;

class FormsController extends Controller
{
    public function index(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $q = $r->query('q', null);
        $status = $r->query('status', null);
        $sort = (string)$r->query('sort', 'updated_at');
        $dir  = (string)$r->query('dir', 'DESC');
        $page = max(1, (int)$r->query('page', 1));
        $perPage = 20;

        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $createdBy = AuthGuard::isAdmin() ? null : AuthGuard::id();
        $result = $repo->paginate(is_string($q) ? $q : null, is_string($status) ? $status : null, $sort, $dir, $page, $perPage, $createdBy);

        return $this->render('forms/list', [
            'pageTitle' => $this->c->get('translator')->t('form.list.title'),
            'rows'      => $result['rows'],
            'total'     => $result['total'],
            'page'      => $page,
            'perPage'   => $perPage,
            'q'         => $q,
            'status'    => $status,
            'sort'      => $sort,
            'dir'       => $dir,
        ]);
    }

    public function create(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $title = trim((string)$r->input('title', '未命名表單'));
        if ($title === '') $title = '未命名表單';
        $id = $repo->create((int)AuthGuard::id(), $title);
        Session::flash('success', $this->c->get('translator')->t('common.success'));
        return $this->redirect('/forms/' . $id . '/edit');
    }

    public function edit(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->loadFull($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        return $this->render('forms/edit', [
            'pageTitle' => $this->c->get('translator')->t('form.builder.title'),
            'form'      => $form,
        ]);
    }

    public function delete(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        $repo->delete($id);
        Session::flash('success', $this->c->get('translator')->t('common.success'));
        return $this->redirect('/forms');
    }

    public function publish(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        $repo->publish($id);
        Session::flash('success', $this->c->get('translator')->t('common.success'));
        return $this->redirect('/forms/' . $id . '/edit');
    }

    public function unpublish(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        $repo->unpublish($id);
        return $this->redirect('/forms/' . $id . '/edit');
    }

    public function close(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        $repo->close($id);
        return $this->redirect('/forms/' . $id . '/edit');
    }

    public function duplicate(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->find($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        $newId = $repo->duplicate($id, (int)AuthGuard::id());
        Session::flash('success', $this->c->get('translator')->t('common.success'));
        return $this->redirect('/forms/' . $newId . '/edit');
    }

    public function preview(Request $r): Response
    {
        if (($x = $this->requireLogin()) !== null) return $x;
        $id = (int)$r->params['id'];
        /** @var FormRepository $repo */
        $repo = $this->c->get('formRepo');
        $form = $repo->loadFull($id);
        if (!$form) return Response::text('Not Found', 404);
        if (!$this->canEdit($form)) return Response::text('Forbidden', 403);
        return $this->render('forms/fill', [
            'pageTitle' => $this->c->get('translator')->t('form.preview.title'),
            'form'      => $form,
            'isPreview' => true,
        ]);
    }

    private function canEdit(array $form): bool
    {
        if (AuthGuard::isAdmin()) return true;
        return (int)$form['created_by'] === (int)AuthGuard::id();
    }
}
