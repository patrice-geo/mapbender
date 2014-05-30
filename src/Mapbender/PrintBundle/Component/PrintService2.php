<?php

namespace Mapbender\PrintBundle\Component;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use TCPDF_IMPORT as TCPDF;
use TCPDF_FONTS;


class PrintService2
{
    protected $container;
    protected $data;
    protected $pdf;
    protected $tempFiles;

    /**
     * PrintService2 constructor
     * 
     * @param ContainerInterface $container Symfony's service container
     */
    public function __construct(ContainerInterface $container)
    {
            $this->container = $container;
    }

    public function doPrint($content, $mode=null)
    {
        $this->data = json_decode($content, true);
        $odgParser = new OdgParser($this->container);
        $this->conf = $odgParser->getConf($this->data['template']);

        $this->createPdf();
        $this->addBackground();
        $this->addMapLayers();
        $this->addOrientationArrow();
        $this->addMapGrid();
        $this->addFormFields();
        $this->setMetadata();
        $this->setupViewer();

        return $this->getPdfBytes();
    }

    protected function createPdf()
    {
        $format = $this->data['format'];

        switch($format) {
                case 'a2': $format = array(420, 594); break;
                case 'a1': $format = array(594, 841); break;
                case 'a0': $format = array(841, 1189); break;
        }

        $this->pdf = new TCPDF($this->conf['orientation'], 'mm', $format, true, 'UTF-8', false);
        $this->pdf->SetAutoPageBreak(false);
        $this->pdf->addPage();
    }

    protected function addBackground()
    {
        // @todo: Use file locator
        $resource_dir = $this->container->getParameter('kernel.root_dir') . '/Resources/MapbenderPrintBundle';
        $template = $this->data['template'];
        $this->pdffile = $resource_dir . '/templates/' . $template . '.pdf';
        
        // There's a stray print_r in the importPDF method, so we silence the method by hand        
        ob_start();
        $this->pdf->importPDF($this->pdffile);
        ob_end_clean();
    }

    protected function addMapLayers()
    {
        $rotation = $this->data['rotation'];     
        $quality = $this->data['quality'];
        $mapWidth = $this->data['extent']['width'];
        $mapHeight = $this->data['extent']['height'];
        $centerX = $this->data['center']['x'];
        $centerY = $this->data['center']['y'];
        $imageWidth = round($this->conf['map']['width'] / 2.54 * $quality);
        $imageHeight = round($this->conf['map']['height'] / 2.54 * $quality);
        
        // Need to keep the original values as these get changed when rotated
        $finalMapWidth = $mapWidth;
        $finalMapHeight = $mapHeight;
        $finalImageWidth = $imageWidth;
        $finalImageHeight = $imageHeight;
        $scaleX = 1.0;
        $scaleY = 1.0;

        // Extend BBOX and image size to fit rotated AOI
        if(0 != $rotation) {
            // dimensions of a rotated rectangle: http://stackoverflow.com/a/9972699/361488
            $cr = cos(deg2rad(-$rotation));
            $cr2 = $cr * $cr;
            $sr = sin(deg2rad(-$rotation));
            $sr2 = $sr * $sr;
            
            $mapWidth = (1 / ($cr2 - $sr2)) * ($finalMapWidth * $cr - $finalMapHeight * $sr);
            $mapHeight = (1 / ($cr2 - $sr2)) * (-$finalMapWidth * $sr + $finalMapHeight * $cr);

            $imageWidth = (1 / ($cr2 - $sr2)) * ($finalImageWidth * $cr - $finalImageHeight * $sr);
            $imageHeight = (1 / ($cr2 - $sr2)) * (-$finalImageWidth * $sr + $finalImageHeight * $cr);

            $scaleX= $finalImageWidth / $imageWidth;
            $scaleY = $finalImageHeight / $imageHeight;
        }

        $bbox = array(
            $centerX - $mapWidth * 0.5,
            $centerY - $mapHeight * 0.5,
            $centerX + $mapWidth * 0.5,
            $centerY + $mapHeight * 0.5
        );

        $mapBox = array(
            $this->conf['map']['x'] * 10,
            $this->conf['map']['y'] * 10,
            $this->conf['map']['width'] * 10,
            $this->conf['map']['height'] * 10);

        $this->tempFiles = array();
        foreach ($this->data['layers'] as $i => $layer) {
            switch($layer['type']) {
                case 'wms':
                    $this->addWmsLayer($layer, $bbox, $imageWidth, $imageHeight, $rotation, $scaleX, $scaleY, $mapBox);
                    break;
                case 'GeoJSON+Style':
                    $this->addFeatureLayer($layer, $bbox, $rotation, $scaleX, $scaleY, $mapBox);
                    break;
                default:
                    throw new RuntimeException('Unhandled layer type ' . $layer['type']);
            }
        }

        // Be nice an clean up
        $this->pdf->setAlpha(1, 'Normal', 1);
        foreach($this->tempFiles as $tempFile) {
            unlink($tempFile);
        }
        $this->tempFiles = array();

        call_user_func_array(array(&$this->pdf, 'rect'), $mapBox);
    }

    protected function addOrientationArrow()
    {

    }

    protected function addMapGrid()
    {

    }

    protected function addFormFields()
    {
        $this->pdf->startLayer($this->container->get('translator')->trans('Form'), true, true, false);
        foreach ($this->conf['fields'] as $k => $v) {
            // @todo: Try original font, fallback to Dejavu Sans
            $this->pdf->SetFont('Dejavu Sans', '', $this->conf['fields'][$k]['fontsize'], TCPDF_FONTS::_getfontpath() . 'dejavusans.php');

            $this->pdf->SetXY(
                $this->conf['fields'][$k]['x'] * 10,
                $this->conf['fields'][$k]['y'] * 10);

            switch ($k) {
                case 'date' :
                    // @todo: Use intl extension with current locale
                    $date = new \DateTime;
                    $this->pdf->Cell(
                        $this->conf['fields']['date']['width'] * 10,
                        $this->conf['fields']['date']['height'] * 10,
                        $date->format('d.m.Y'));
                    break;
                case 'scale' :
                    if (isset($this->data['scale_select'])) {
                        $this->pdf->Cell(
                            $this->conf['fields']['scale']['width'] * 10,
                            $this->conf['fields']['scale']['height'] * 10,
                            '1 : ' . $this->data['scale_select']);
                    } else {
                        $this->pdf->Cell(
                            $this->conf['fields']['scale']['width'] * 10,
                            $this->conf['fields']['scale']['height'] * 10,
                            '1 : ' . $this->data['scale_text']);
                    }
                    break;
                default:
                    if (isset($this->data['extra'][$k])) {
                        $this->pdf->MultiCell(
                            $this->conf['fields'][$k]['width'] * 10,
                            $this->conf['fields'][$k]['height'] * 10,
                            utf8_decode($this->data['extra'][$k]));
                    }
                    break;
            }
        }
        $this->pdf->endLayer();
    }

    protected function setMetadata()
    {
        $this->pdf->setTitle('X-Title');
        $this->pdf->setSubject('X-Subject');
        $this->pdf->setAuthor('X-Author');
        $this->pdf->setKeywords('X-Keywords');
        $this->pdf->setCreator('Mapbender3');
    }

    protected function setupViewer()
    {
        $this->pdf->setViewerPreferences(array(
            'FitWindow' => true
        ));
    }

    protected function addQrCode()
    {
    }

    protected function getPdfBytes($mode=null)
    {
            $this->pdf->output('test.pdf', $mode ? $mode : 'D');
    }

    protected function addWmsLayer($layer, $bbox, $imageWidth, $imageHeight, $rotation, $scaleX, $scaleY, $mapBox)
    {
            // @todo: Is this a safe replacement?
            $url = strstr($layer['url'], '&BBOX', true);

            $url = sprintf('%s&BBOX=%s&WIDTH=%d&HEIGHT=%d', $url, implode(',', $bbox), $imageWidth, $imageHeight);

            // @todo:  Replace pattern
            
            // Get image using our proxy magic
            $filename = $this->getImageFromURL($url);
            $this->tempFiles[] = $filename;

            if(0 != $rotation) {
                $filename = $this->rotateImage($filename, $rotation, $scaleX, $scaleY);
                $this->tempFiles[] = $filename;
            }

            // @todo: Use real layer name
            $this->pdf->startLayer('Layer', true, true, false);
            $this->pdf->setAlpha(1, 'Normal', $layer['opacity']);
            $this->pdf->image(
                $filename,
                $mapBox[0],
                $mapBox[1],
                $mapBox[2],
                $mapBox[3]);
            $this->pdf->endLayer();
    }

    protected function addFeatureLayer($layer, $bbox, $rotation, $scaleX, $scaleY, $mapBox)
    {
        $transformer = function($points) use ($mapBox, $bbox, $rotation, $scaleX, $scaleY) {
            array_walk($points, function(&$coords) use ($mapBox, $bbox, $rotation, $scaleX, $scaleY) {
                $x = $coords[0];
                $y = $coords[1];
                // Calculate scale and translation parameters
                $sx = $mapBox[2] / ($bbox[2] - $bbox[0]) / $scaleX;
                $sy = $mapBox[3] / ($bbox[3] - $bbox[1]) / $scaleY;
                $tx = ($bbox[0] + $bbox[2]) / 2;
                $ty = ($bbox[1] + $bbox[3]) / 2;

                // Do transform
                $a = ($x - $tx) * $sx;
                $b = ($ty - $y) * $sy;

                if(0 != $rotation) {
                    $x = deg2rad(-$rotation);
                    $cx = cos($x);
                    $sx = sin($x);
                    $a_ = $a;
                    $b_ = $b;

                    $a = $cx * $a_ - $sx * $b_;
                    $b = $sx * $a_ + $cx * $b_;
                }

                // Finally, shift to map box
                $coords[0] = $a + $mapBox[0] + $mapBox[2] / 2;
                $coords[1] = $b + $mapBox[1] + $mapBox[3] / 2;
            });
            return $points;
        };

        // Draw features
        foreach($layer['geometries'] as $geometry) {
            $renderMethodName = 'render' . $geometry['type'];
            if(!method_exists($this, $renderMethodName)) {
                throw new \RuntimeException('Can not draw geometries of type "' . $geometry['type'] . '".');
            }

            $this->$renderMethodName($geometry, $transformer);
        }
    }

    protected function renderPoint($geometry, $transformer)
    {
        /*
            [fillColor] => #ee9900
            [fillOpacity] => 0.4
            [strokeColor] => #ee9900
            [strokeOpacity] => 1
            [strokeWidth] => 1
            [strokeLinecap] => round
            [strokeDashstyle] => solid
            [pointRadius] => 6
        */
        $this->pdf->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(0, 0, 0)));

        $coords = $transformer(array($geometry['coordinates']));
        $this->pdf->circle($coords[0][0], $coords[0][1], 5, 0, 360, 'DF');
    }

    private function renderLineString($geometry, $transformer)
    {
        /*
            [strokeColor] => #ee9900
            [strokeOpacity] => 1
            [strokeWidth] => 1
            [strokeLinecap] => round
            [strokeDashstyle] => solid
         */
        $points = $transformer($geometry['coordinates']);
        $lineStyle = $this->getLineStyle($geometry['style']);
        $preAlpha = $this->pdf->getAlpha();
        $this->pdf->setAlpha($geometry['style']['strokeOpacity'], 'Normal', $geometry['style']['strokeOpacity'], true);
        $this->pdf->polyline($this->flattenPointsArray($points), 'S', array('all' => $lineStyle));
        //array(4) { ["CA"]=> float(1) ["ca"]=> float(0.85) ["BM"]=> string(7) "/Normal" ["AIS"]=> bool(false) }
    }

    protected function renderPolygon($geometry, $transformer)
    {
            /*
            [fillColor] => #ee9900
            [fillOpacity] => 0.4
            [strokeColor] => #ee9900
            [strokeOpacity] => 1
            [strokeWidth] => 1
            [strokeLinecap] => round
            [strokeDashstyle] => solid
        */
        foreach($geometry['coordinates'] as $ring) {
            if(count($ring) < 3) {
                continue;
            }

            
            $points = $transformer($ring);
            $this->pdf->polygon($this->flattenPointsArray($points), 'DF');
        }
    }

    protected function flattenPointsArray($points) {
        $coords = array();
        array_walk_recursive($points, function($coord) use (&$coords) {
            $coords[] = $coord;
        });

        return $coords;
    }

    protected function getLineStyle(array $style)
    {
        return array(
            'width' => 5* $style['strokeWidth'],
            'cap' => $style['strokeLinecap'],
            'join' => 'round',
            'dash' => 0,  // @todo: parse strokeDashstyle
            'phase' => 0,
            'color' => CSSColorParser::parse($style['strokeColor'])
        );
    }

    protected function getImageFromURL($url)
    {
        $attributes = array();
        $attributes['_controller'] = 'OwsProxy3CoreBundle:OwsProxy:entryPoint';
        $subRequest = new Request(array(
            'url' => $url
            ), array(), $attributes, array(), array(), array(), '');
        $response = $this->container->get('http_kernel')->handle($subRequest,
            HttpKernelInterface::SUB_REQUEST);

        $filename = tempnam(sys_get_temp_dir(), 'mb3_print');

        file_put_contents($filename, $response->getContent());
        $im = null;
        switch (trim($response->headers->get('content-type'))) {
            case 'image/png' :
                $im = imagecreatefrompng($filename);
                break;
            case 'image/jpeg' :
                $im = imagecreatefromjpeg($filename);
                break;
            case 'image/gif' :
                $im = imagecreatefromgif($filename);
                break;
            default:
                continue;
                $this->container->get("logger")->debug("Unknown mimetype " . trim($response->headers->get('content-type')));
        }

        return $filename;
    }

    protected function rotateImage($filename, $rotation, $scaleX, $scaleY)
    {
        // Load image
        $size = getimagesize($filename);
        switch($size['mime']) {
            case 'image/jpeg': $image = imagecreatefromjpeg($filename); break;
            case 'image/gif': $image = imagecreatefromgif($filename); break;
            case 'image/png': $image = imagecreatefrompng($filename); break;
            default:
                throw new RuntimeException('Unhandled image mimetype ' . $size['mime'] . ' in rotation.');
        }

        // Rotate image
        $transColor = imagecolorallocatealpha($image, 255, 255, 255, 127);
        $rotatedImage = imagerotate($image, $rotation, $transColor);

        //header('Content-Type: image/png');
        //imagepng($image);

        // Clip image        
        $width = round(imagesx($image) * $scaleX);
        $height = round(imagesy($image) * $scaleY);
        $clippedImage = imagecreatetruecolor($width, $height);
        imagealphablending($clippedImage, false);
        imagesavealpha($clippedImage, true);
        imagecopy(
            $clippedImage, $rotatedImage,
            0, 0,
            (imagesx($rotatedImage) - $width) / 2, (imagesy($rotatedImage) - $height) / 2, 
            $width, $height);

        $filename = tempnam(sys_get_temp_dir(), 'mb3_print');
        switch($size['mime']) {
            case 'image/jpeg': imagejpeg($clippedImage, $filename); break;
            case 'image/gif': imagegif($clippedImage, $filename); break;
            case 'image/png': imagepng($clippedImage, $filename); break;
        }

        return $filename;
    }
}
