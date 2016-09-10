<?php

namespace Tsos\ImageProcessingBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('TsosImageProcessingBundle:Default:index.html.twig');
    }
}
