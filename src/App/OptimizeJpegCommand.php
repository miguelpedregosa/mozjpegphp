<?php
/**
 *
 * User: migue
 * Date: 15/02/15
 * Time: 14:16
 */

namespace MozJpegPhp\App;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class OptimizeJpegCommand extends Command
{
    private $mozjpeg_path = '/opt/mozjpeg/bin';
    private $optimized_images = array();
    private $encode_quality = null;
    private $source = null;
    private $destination_dir = null;
    private $supported_images = array(
        IMAGETYPE_JPEG,
        IMAGETYPE_BMP
    );

    /**
     *
     */
    protected function configure()
    {
        $this->setName('optimize')
            ->setDescription('Optimize images using MozJpeg lib <https://github.com/mozilla/mozjpeg>')
            ->addArgument(
                'source',
                InputArgument::OPTIONAL,
                'File o directory to optimize'
            )
            ->addOption(
                'q',
                null,
                InputOption::VALUE_OPTIONAL,
                'Quality for jpeg file recoding (if none, optimization will be lossless)',
                null
            )
            ->addOption(
                'd',
                null,
                InputOption::VALUE_OPTIONAL,
                'Destiniation directory (if none, "optimized" dir will be created in source dir',
                null
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            if (!$this->checkMozJpegLibs()) {
                $message = "MozJpeg lib (https://github.com/mozilla/mozjpeg) not installed\n\n";
                $message .= sprintf(
                    "Must be installed in %s or change the path in %s",
                    $this->mozjpeg_path,
                    __FILE__
                );
                throw new \Exception($message);
            }

            //Inicializamos en el entorno a partir de las opciones que se pasan por linea de comandos
            $this->setUpEnvironment($input);

            //El origen puede ser un fichero o un directorio con imagenes
            if (is_file($this->source)) {
                $this->proccessFile($output, $this->source);
            } elseif (is_dir($this->source)) {
                if ($handle = opendir($this->source)) {
                    while (false !== ($entry = readdir($handle))) {
                        if ($entry != "." && $entry != ".." && !is_dir($entry) && $this->isSupportedImage($entry)) {
                            $this->proccessFile($output, $entry);
                        }
                    }
                    closedir($handle);
                }
            }
            $this->printStats($output);

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
        return 0;
    }

    /**
     * @return bool
     */
    private function checkMozJpegLibs()
    {
        return is_executable($this->getJpegTranPath()) && is_executable($this->getCjpegPath());
    }

    /**
     * @return string
     */
    private function getJpegTranPath()
    {
        return $this->mozjpeg_path . '/jpegtran';
    }

    /**
     * @return string
     */
    private function getCjpegPath()
    {
        return $this->mozjpeg_path . '/cjpeg';
    }

    /**
     * @param InputInterface $input
     * @throws \Exception
     */
    private function setUpEnvironment(InputInterface $input)
    {
        //Calidad de la recodificacion del fichero (si se pasa)
        $encode_quality = $input->getOption('q');
        if (is_numeric($encode_quality) && $encode_quality < 0) {
            throw new \Exception('Quality for image recoding must be grater than 0');
        }
        $this->encode_quality = is_numeric($encode_quality) ? (int)$encode_quality : null;

        //Origen, si no se proporciona se usa el directorio actual
        $source = $input->getArgument('source');
        if (!$source) {
            $source = getcwd();
        }
        $this->source = $source;

        //Directorio de destino
        $dest = $this->getDestinationDirectory($input->getOption('d'));
        if (!$dest) {
            throw new \Exception('Invalid destination dir');
        }
        $this->destination_dir = $dest;

    }

    /**
     * @param null $dest
     * @return bool|null|string
     */
    private function getDestinationDirectory($dest = null)
    {
        //Directorio por defecto
        if (is_null($dest)) {
            $dest = (is_numeric($this->encode_quality)) ? sprintf('optimized_%d', $this->encode_quality) : 'optimized';
        }

        if (is_dir($dest) && is_writable($dest)) {
            return $dest;
        }
        if (mkdir($dest)) {
            return $dest;
        }
        return false;
    }

    /**
     * @param OutputInterface $output
     * @param $source
     * @throws \Exception
     */
    private function proccessFile(OutputInterface $output, $source)
    {
        if (!is_file($source)) {
            {
                throw new \Exception(sprintf('Invalid file %s', $source));
            }
        }
        $source_size = filesize($source);
        $pathinfo = pathinfo($source);
        $dest_file = $this->destination_dir . '/' . $pathinfo['filename'] . '.jpg';

        //Encode (cjpeg)
        $jpeg = $this->encodeImageToJpeg($source);


        //Transcode (jpegtran)
        exec(sprintf("%s -copy none %s > %s", $this->getJpegTranPath(), $jpeg, $dest_file));
        unlink($jpeg);
        $dest_size = filesize($dest_file);
        $output->writeln(
            sprintf(
                'Optimizing image "%s"%s: %s --> %s',
                $source,
                ($this->encode_quality) ? ' (' . (int)$this->encode_quality . '%)' : '',
                $this->humanFilesize($source_size),
                $this->humanFilesize($dest_size)
            )
        );
        $this->optimized_images[] = $source_size - $dest_size;
    }

    /**
     * @param $source
     * @return string
     * @throws \Exception
     */
    private function encodeImageToJpeg($source)
    {
        if (exif_imagetype($source) !== IMAGETYPE_JPEG || $this->encode_quality) {
            $quality = ($this->encode_quality) ? (int)$this->encode_quality : 100;
            exec(
                sprintf(
                    '%s -quality %d -outfile /tmp/mozjpeg_tmp.jpg %s',
                    $this->getCjpegPath(),
                    $quality,
                    $source
                )
            );
        } else {
            copy($source, '/tmp/mozjpeg_tmp.jpg');
        }

        if (!is_file('/tmp/mozjpeg_tmp.jpg')) {
            {
                throw new \Exception('Error creating temp file');
            }
        }
        return '/tmp/mozjpeg_tmp.jpg';
    }

    /**
     * FROM http://php.net/manual/es/function.filesize.php#106569
     * @param $bytes
     * @param int $decimals
     * @return string
     */
    private function humanFilesize($bytes, $decimals = 2)
    {
        $sz = 'BKMGTP';
        $factor = floor((strlen($bytes) - 1) / 3);
        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$sz[$factor];
    }

    /**
     * @param $source
     * @return bool
     */
    private function isSupportedImage($source)
    {
        return in_array(exif_imagetype($source), $this->supported_images);

    }

    /**
     * @param OutputInterface $output
     */
    private function printStats(OutputInterface $output)
    {
        if (count($this->optimized_images)) {
            $gain = 0;
            array_walk(
                $this->optimized_images,
                function ($elem) use (&$gain) {
                    $gain += $elem;
                }
            );
            $output->writeln(
                sprintf(
                    "Optimized %d files%s with gain of %s",
                    count($this->optimized_images),
                    is_numeric($this->encode_quality) ? ' (recoding at ' . (int)$this->encode_quality . '% of original quality)' : '',
                    $this->humanFilesize($gain)
                )
            );
        }


    }

}
