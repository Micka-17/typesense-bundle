<?php

namespace Micka17\TypesenseBundle\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

class EntityController extends AbstractController
{
    #[Route('/admin/entities', name: 'micka17_typesense_admin_entities')]
    public function index(): Response
    {
        return $this->render('admin/entities/index.html.twig');
    }
}
