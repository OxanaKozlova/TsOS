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
use Ob\HighchartsBundle\Highcharts\Highchart;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

/**
 * Image controller.
 *
 * @Route("/")
 */
class ImageController extends Controller
{
    const SIZE = 256;

    protected $width;

    protected $height;

    protected $bright = null;

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
     * @Route("/{id}/show_bright_bar_chart", name="image_show_bright_bar_chart")
     * @Method("GET")
     */
    public function showBrightBarChartAction(Image $image)
    {
        if($this->bright === null) {
            $this->bright = $this->getBrightness($image);
        }

        $chart = $this->getBarChart($this->bright);
        return $this->render('image/show_bright_bar_chart.html.twig', array(
            'chart' => $chart,
            'image' => $image,
        ));
    }

    public function getBarChart($bright)
    {
        $series = array(
            array(
                "data" => $this->createBarChart($bright),
                'color' => '#008080')
        );
        $ob = new Highchart();
        $ob->chart->renderTo('linechart');  // The #id of the div where to render the chart
        $ob->chart->type('column');
        $ob->xAxis->categories($this->getBoundaryValue($bright));
        $ob->title->text('Гистограмма');
        $ob->series($series);
        return $ob;
    }

    public function createBarChart($bright)
    {
        $boundary_value = $this->getBoundaryValue($bright);

        $bar_chart = [];
        for($i = 0; $i <= self::SIZE; $i++) {
            $bar_chart[] = 0;
        }

        $bright_array_size = count($bright);

        for($i = 1; $i < self::SIZE; $i++) {
            for($j = 0; $j < $bright_array_size; $j++){
                if($bright[$j] <= $boundary_value[$i] && $bright[$j] >= $boundary_value[$i-1]){
                    $bar_chart[$i-1] ++;
                }
            }
        }
        return $bar_chart;
    }

    private function getBoundaryValue($bright)
    {
        $min = min($bright);
        $max = max($bright);
        $boundary_value = [];
        $r = $max - $min;
        $delta = (float)$r / (float)self::SIZE;

        if( $delta == 0) {
            $boundary_value[] = min($bright);
            return $boundary_value;
        }

        for ($i = $min; $i <= $max; $i += $delta) {
            $boundary_value[] = $i;
        }
        $boundary_value[] = $max;

        return $boundary_value;
    }

    public function getBrightness(Image $image)
    {
        $path = $this->get('itm.file.preview.path.resolver')->getPath($image, $image->getImage());

        $rgbArray = $this->getRgbArray($path);
        $bright = [];
        foreach ($rgbArray as $rgbRow) {
            foreach ($rgbRow as $rgb) {
                $bright[] = 0.3 * $rgb['red'] + 0.59 * $rgb['green'] + 0.11 * $rgb['blue'];
            }
        }

        return $bright;
    }

    public function getRgbArray($path)
    {
        $size = getimagesize($path);
        $this->width = $size[0];
        $this->height = $size[1];

        $image = 0;
        if (exif_imagetype($path) === IMAGETYPE_JPEG) {
            $image = imagecreatefromjpeg($path);            //возвращает идентификатор изображения
        }
        elseif (exif_imagetype($path) === IMAGETYPE_PNG) {
                $image = imagecreatefrompng($path);
        }

        $pixels = [];
        $colors = [];
        for ($i = 0; $i < $this->width; $i++) {
            for ($j = 0; $j < $this->height; $j++) {
                $pixels[$i][$j] = imagecolorat($image, $i, $j); //получение цвета пикселя
                $colors[$i][$j] = imagecolorsforindex($image, $pixels[$i][$j]); //получение rgb массива для каждого пикселя
            }
        }

        return $colors;
    }

    /**
     * @Route("/{id}/create_gamma_correction", name="image_create_gamma_correction")
     */
    public function createGammaCorrectionAction(Request $request)
    {
        $data_form = array();
        $form = $this->createFormBuilder($data_form)
            ->add('c', TextType::class)
            ->add('y', TextType::class)
            ->add('id', HiddenType::class)
            ->add('save', SubmitType::class, array('label' => 'Gamma Correction'))
            ->getForm();
        $form['id']->setData($request->get('id'));
        if ($request->isMethod('POST')) {
            $form->handleRequest($request);
            $data = $form->getData();
            $response = $this->forward('TsosImageProcessingBundle:Image:showGammaCorrection', array(
                'id' => $data['id'],
                'c' => (float)$data['c'],
                'y' => (float)$data['y'],
            ));
            //return $this->redirectToRoute('bar_chart', array("random" => 5));
            return$response;
        }
        return $this->render('image/create_gamma_correction.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/{id}/show_gamma_correction", name="image_show_gamma_correction")
     * @Method("GET")
     */
    public function showGammaCorrectionAction(Request $request, $id, $c, $y)
    {
        $image = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('TsosImageProcessingBundle:Image')
            ->findOneBy(['id' => $id]);

        $chart = $this->getBarChart($this->setGammaCorrection($c, $y, $image));
        return $this->render('image/show_gamma_correction.html.twig', array(
            'chart' => $chart,
            'image' => $image,
        ));
    }

    public function setGammaCorrection($c, $y, $image)
    {
        if($this->bright === null) {
            $this->bright = $this->getBrightness($image);
        }

        $bright_size = count($this->bright);

        $gamma = [];
        for($i = 0; $i < $bright_size; $i++) {
            $gamma[] = $c * pow($this->bright[$i], $y);
        }

        return $gamma;
    }
}
