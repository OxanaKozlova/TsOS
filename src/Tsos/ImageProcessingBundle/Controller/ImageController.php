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

    const GAMMA_FILENAME = '/home/oxana/projects/TsOS/LAB1/web/uploads/Tsos/ImageProcessingBundle/Entity/Image/gamma.jpeg';

    const FILTER_FILENAME = '/home/oxana/projects/TsOS/LAB1/web/uploads/Tsos/ImageProcessingBundle/Entity/Image/filter.jpeg';

    protected $width;

    protected $height;

    protected $bright;

    protected $rgbArray;

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








    public function getPath(Image $image)
    {
        $path = $this->get('itm.file.preview.path.resolver')->getPath($image, $image->getImage());
        return $path;
    }

    /**
     * @Route("/{id}/show_bright_bar_chart", name="image_show_bright_bar_chart")
     * @Method("GET")
     */
    public function showBrightBarChartAction(Image $image)
    {
        $path = $this->getPath($image);
        $this->rgbArray = $this->getRgbArray($path);
        $this->bright = $this->getBrightnessMatrix($this->rgbArray);

        $chart = $this->getBarChart($this->bright, 'Гистограмма яркости');
        return $this->render('image/show_bar_chart.html.twig', array(
            'chart' => $chart,
            'image' => $image,
        ));
    }

    public function getBarChart($bright, $title)
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
        $ob->title->text($title);
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

        for($i = 1; $i < self::SIZE; $i++) {
            for ($k = 0; $k < $this->height; $k++) {
                for ($j = 0; $j < $this->width; $j++) {
                    if ($bright[$k][$j] <= $boundary_value[$i] && $bright[$k][$j] >= $boundary_value[$i - 1]) {
                        $bar_chart[$i - 1]++;
                    }
                }
            }
        }
        return $bar_chart;
    }

    private function getBoundaryValue($bright)
    {
        $min_array = [];
        $max_array = [];
        foreach ($bright as $brightRow) {
            $min_array[] = min($brightRow);
            $max_array[] = max($brightRow);
        }

        $min = min($min_array);
        $max = max($max_array);

        $boundary_value = [];
        $r = $max - $min;
        $delta = (float)$r / (float)self::SIZE;

        if( $delta == 0) {
            $boundary_value[] = $min;
            return $boundary_value;
        }

        for ($i = $min; $i <= $max; $i += $delta) {
            $boundary_value[] = $i;
        }
        $boundary_value[] = $max;

        return $boundary_value;
    }

//    public function getBrightness($rgbArray)
//    {
//        $bright = [];
//        foreach ($rgbArray as $rgbRow) {
//            foreach ($rgbRow as $rgb) {
//                $bright[] = 0.3 * $rgb['red'] + 0.59 * $rgb['green'] + 0.11 * $rgb['blue'];
//            }
//        }
//
//        return $bright;
//    }

    public function getBrightnessMatrix($rgbArray)
    {
        $bright = [];
        for ($i = 0; $i < $this->height; $i++) {
            for ($j = 0; $j < $this->width; $j++) {
                $bright[$i][$j] = 0.3 * $rgbArray[$i][$j]['red']
                    + 0.59 * $rgbArray[$i][$j]['green']
                    + 0.11 * $rgbArray[$i][$j]['blue'];
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
        for ($i = 0; $i < $this->height; $i++) {
            for ($j = 0; $j < $this->width; $j++) {
                $pixels[$i][$j] = imagecolorat($image, $j, $i); //получение цвета пикселя
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
    public function showGammaCorrectionAction(Image $image, $id, $c, $y)
    {
        $image = $this
            ->getDoctrine()
            ->getManager()
            ->getRepository('TsosImageProcessingBundle:Image')
            ->findOneBy(['id' => $id]);

        $chart = $this->getBarChart($this->setGammaCorrection($c, $y, $image), 'Гистограмма гамма-коррекции');
        return $this->render('image/show_bar_chart.html.twig', array(
            'chart' => $chart,
            'image' => $image,
        ));
    }

    public function setGammaCorrection($c, $y, $image)
    {
        $path = $this->getPath($image);
        $this->rgbArray = $this->getRgbArray($path);
        $this->bright = $this->getBrightnessMatrix($this->rgbArray);


        $gamma = $this->rgbArray;
        for($i = 0; $i < $this->height; $i++) {
            for($j = 0; $j < $this->width; $j++) {
                $gamma[$i][$j]['red'] = $c * pow($this->rgbArray[$i][$j]['red'], $y);
                $gamma[$i][$j]['green'] = $c * pow($this->rgbArray[$i][$j]['green'], $y);
                $gamma[$i][$j]['blue'] = $c * pow($this->rgbArray[$i][$j]['blue'], $y);

                $gamma[$i][$j]['red'] = $this->checkRange($gamma[$i][$j]['red']);
                $gamma[$i][$j]['green'] = $this->checkRange($gamma[$i][$j]['green']);
                $gamma[$i][$j]['blue'] = $this->checkRange($gamma[$i][$j]['blue']);
            }
        }


        $this->createImage($gamma, self::GAMMA_FILENAME);
        return $this->getBrightnessMatrix($gamma);
    }

    public function checkRange($color) {
        if($color > 255)
            return 255;
        if($color < 0)
            return 0;

        return $color;
    }

    public function createImage($rgdArray, $filename)
    {
        $image = imagecreatetruecolor($this->width, $this->height);

        for($i = 0; $i < $this->height; $i++) {
            for($j = 0; $j < $this->width; $j++) {
                imagesetpixel($image, $j, $i, imagecolorallocatealpha($image,
                    $rgdArray[$i][$j]['red'],
                    $rgdArray[$i][$j]['green'],
                    $rgdArray[$i][$j]['blue'],
                    $this->rgbArray[$i][$j]['alpha']
                    ));
            }
        }

        imagejpeg($image, $filename);
    }


    /**
     * @Route("/{id}/filter", name="image_filter")
     * @Method("GET")
     */
    public function filterAction(Image $image)
    {
        $path = $this->getPath($image);
        $this->rgbArray = $this->getRgbArray($path);
        $this->bright = $this->getBrightnessMatrix($this->rgbArray);

        $chart = $this->getBarChart($this->createFilter($this->rgbArray), 'Гистограмма высокочастотного фильтра');
        return $this->render('image/show_bar_chart.html.twig', array(
            'chart' => $chart,
            'image' => $image,
        ));
    }

    public function createFilter($bright)
    {
        $bright_count = count($bright);
        $f = [];
        for($i = 0; $i < $bright_count; $i++) {
            $line_count = count($bright[$i]);
            for($j = 0; $j < $line_count; $j++ ){
                $i1 = -1;
                $i2 = 1;
                $j1 = -1;
                $j2 = 1;
                if($i <= 0){
                    $i1 = 0;
                }
                if($i >=($bright_count-1)){
                    $i2 = 0;
                }
                if($j <= 0){
                    $j1 = 0;
                }
                if($j >=($line_count - 1)){
                    $j2 = 0;
                }
                $f[$i][$j]['red'] =
                    (-1) * $bright[$i+$i1][$j+$j1]['red'] + (-1) * $bright[$i+$i1][$j]['red'] + (-1)*$bright[$i+$i1][$j+$j2]['red']
                    +(-1) * $bright[$i][$j+$j1]['red'] + 9 * $bright[$i][$j]['red'] + (-1) * $bright[$i][$j+$j2]['red']
                    +(-1) * $bright[$i+$i2][$j+$j1]['red'] + (-1) * $bright[$i+$i2][$j]['red'] + (-1) * $bright[$i+$i2][$j+$j2]['red'];
                $f[$i][$j]['red'] = $this->checkRange($f[$i][$j]['red']);

                $f[$i][$j]['green'] =
                    (-1) * $bright[$i+$i1][$j+$j1]['green'] + (-1) * $bright[$i+$i1][$j]['green'] + (-1)*$bright[$i+$i1][$j+$j2]['green']
                    +(-1) * $bright[$i][$j+$j1]['green'] + 9 * $bright[$i][$j]['green'] + (-1) * $bright[$i][$j+$j2]['green']
                    +(-1) * $bright[$i+$i2][$j+$j1]['green'] + (-1) * $bright[$i+$i2][$j]['green'] + (-1) * $bright[$i+$i2][$j+$j2]['green'];

                $f[$i][$j]['green'] = $this->checkRange($f[$i][$j]['green']);

                $f[$i][$j]['blue'] =
                    (-1) * $bright[$i+$i1][$j+$j1]['blue'] + (-1) * $bright[$i+$i1][$j]['blue'] + (-1)*$bright[$i+$i1][$j+$j2]['blue']
                    +(-1) * $bright[$i][$j+$j1]['blue'] + 9 * $bright[$i][$j]['blue'] + (-1) * $bright[$i][$j+$j2]['blue']
                    +(-1) * $bright[$i+$i2][$j+$j1]['blue'] + (-1) * $bright[$i+$i2][$j]['blue'] + (-1) * $bright[$i+$i2][$j+$j2]['blue'];

                $f[$i][$j]['blue'] = $this->checkRange($f[$i][$j]['blue']);
            }



        }

        $this->createImage($f, self::FILTER_FILENAME);
        return $this->getBrightnessMatrix($f);
    }
}
