<?php

namespace Mapbender\PrintBundle\Component;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use TCPDF_IMPORT as TCPDF;
use TCPDF_FONTS;


class PrintService2
{
    protected $container;
    protected $data;
    protected $pdf;

    public function __construct($container)
    {
            $this->container = $container;
    }

    public function doPrint($content, $mode=null)
    {
        $this->data = json_decode($content, true);
        $odgParser = new OdgParser($this->container);
        $this->conf = $odgParser->getConf($this->data['template']);

print_r($this->data);die;

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


        // Extend BBOX and image size to fit rotated AOI
        if(0 != $rotation) {
            $mapHeight = round(abs(sin(deg2rad($rotation)) * $finalMapHeight) + abs(cos(deg2rad($rotation)) * $finalMapWidth));
            $mapWidth = round(abs(sin(deg2rad($rotation)) * $finalMapWidth) + abs(cos(deg2rad($rotation)) * $finalMapHeight));

            $imageWidth = round(abs(sin(deg2rad($rotation)) * $finalImageHeight) + abs(cos(deg2rad($rotation)) * $finalImageWidth));
            $imageHeight = round(abs(sin(deg2rad($rotation)) * $finalImageWidth) + abs(cos(deg2rad($rotation)) * $finalImageHeight));
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

        $tempFiles = array();
        foreach ($this->data['layers'] as $i => $layer) {
            // @todo: Is this a safe replacement?
            $url = strstr($this->data['layers'][$i]['url'], '&BBOX', true);
            $opacity = $this->data['layers'][$i]['opacity']*100;

            $url = sprintf('%s&BBOX=%s&WIDTH=%d&HEIGHT=%d', $url, implode(',', $bbox), $imageWidth, $imageHeight);

            // @todo:  Replace pattern
            
            $this->container->get("logger")->debug("Print Request Nr.: " . $i . ' ' . $url);

            // Get image using our proxy magic
            $filename = $this->getImageFromURL($url);
            $tempFiles[] = $filename;

            if(0 != $rotation) {
                $filename = $this->rotateImage($filename, $rotation, $finalImageWidth, $finalImageHeight);
                $tempFiles[] = $filename;
            }

            // @todo: Use real layer name
            $this->pdf->startLayer('Layer ' . $i, true, true, false);
            $this->pdf->setAlpha(1, 'Normal', $this->data['layers'][$i]['opacity']);
            $this->pdf->image(
                $filename,
                $mapBox[0],
                $mapBox[1],
                $mapBox[2],
                $mapBox[3]);
            $this->pdf->endLayer();
        }

        // Be nice an clean up
        $this->pdf->setAlpha(1, 'Normal', 1);
        foreach($tempFiles as $tempFile) {
            unlink($tempFile);
        }

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

    protected function rotateImage($filename, $rotation, $width, $height)
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

        // Clip image        
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
