<?php

namespace Tsos\ImageProcessingBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Tsos\ImageProcessingBundle\Entity\Image;
use Tsos\ImageProcessingBundle\Form\ImageType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use ITM\ImagePreviewBundle\Resolver\PathResolver;

/**
 * Image controller.
 *
 * @Route("/")
 */
class ImageController extends Controller
{
    /**
     * Lists all Image entities.
     *
     * @Route("/", name="image_index")
     * @Method("GET")
     */
    public function indexAction()
    {
        $em = $this->getDoctrine()->getManager();

        $images = $em->getRepository('TsosImageProcessingBundle:Image')->findAll();

        return $this->render('image/index.html.twig', array(
            'images' => $images,
        ));
    }

    /**
     * Creates a new Image entity.
     *
     * @Route("/new", name="image_new")
     * @Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $image = new Image();
        $form = $this->createForm('Tsos\ImageProcessingBundle\Form\ImageType', $image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($image);
            $em->flush();

            return $this->redirectToRoute('image_show', array('id' => $image->getId()));
        }

        return $this->render('image/new.html.twig', array(
            'image' => $image,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a Image entity.
     *
     * @Route("/{id}", name="image_show")
     * @Method("GET")
     */
    public function showAction(Image $image)
    {
        $deleteForm = $this->createDeleteForm($image);

        return $this->render('image/show.html.twig', array(
            'image' => $image,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing Image entity.
     *
     * @Route("/{id}/edit", name="image_edit")
     * @Method({"GET", "POST"})
     */
    public function editAction(Request $request, Image $image)
    {
        $deleteForm = $this->createDeleteForm($image);
        $editForm = $this->createForm('Tsos\ImageProcessingBundle\Form\ImageType', $image);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($image);
            $em->flush();

            return $this->redirectToRoute('image_edit', array('id' => $image->getId()));
        }

        return $this->render('image/edit.html.twig', array(
            'image' => $image,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a Image entity.
     *
     * @Route("/{id}", name="image_delete")
     * @Method("DELETE")
     */
    public function deleteAction(Request $request, Image $image)
    {
        $form = $this->createDeleteForm($image);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($image);
            $em->flush();
        }

        return $this->redirectToRoute('image_index');
    }

    /**
     * Creates a form to delete a Image entity.
     *
     * @param Image $image The Image entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Image $image)
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('image_delete', array('id' => $image->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    public function getPhotoUrl($id)
    {
        $em = $this->getDoctrine()->getManager();
        $image = $em->getRepository('TsosImageProcessingBundle:Image')->findOneBy(['id' => $id]);
        $url = $this->get('itm.file.preview.path.resolver')->getUrl($image, $image->getImage());
        return $url;
    }

    /**
     * @Route("/{id}/show_bar_chart", name="image_show_bar_chart")
     * @Method("GET")
     */
    public function showBarChart(Image $image)
    {
        $path = $this->get('itm.file.preview.path.resolver')->getPath($image, $image->getImage());
        $rgbArray = $this->getRgbArray($path);
    }

    public function getRgbArray($path)
    {
        $size = getimagesize($path);
        $width = $size[0];
        $height = $size[1];

        $image = imagecreatefrompng($path); //возвращает идентификатор изображения

        $pixels = [];
        $colors = [];
        for ($i = 0; $i < $width; $i++) {
            for ($j = 0; $j < $height; $j++) {
                $pixels[$i][$j] = imagecolorat($image, $i, $j); //получение цвета пикселя
                $colors[$i][$j] = imagecolorsforindex($image, $pixels[$i][$j]); //получение rgb массива для каждого пикселя
            }
        }
        
        return $colors;
    }
}
