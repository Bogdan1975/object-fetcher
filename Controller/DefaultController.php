<?php

namespace Targus\ObjectFetcherBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('TargusObjectFetcherBundle:Default:index.html.twig');
    }
}
