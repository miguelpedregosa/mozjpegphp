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

    /**
     *
     */
    protected function configure()
    {
        $this->setName('optimize')
            ->setDescription('Optimiza imágenes usando la librería MozJpeg')
            ->addArgument(
                'source',
                InputArgument::OPTIONAL,
                'Fichero de origen a optimizar'
            )
            ->addArgument(
                'dest',
                InputArgument::OPTIONAL,
                'Fichero de destino'
            )
            ->addOption(
                'q',
                null,
                InputOption::VALUE_OPTIONAL,
                'Nivel de calidad para recodificar el fichero',
                null
            )
            ->addOption(
                'd',
                null,
                InputOption::VALUE_OPTIONAL,
                'Directorio de destino para las imágenes optimizadas',
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
                $message = "No se ha encontrado la librería MozJpeg (https://github.com/mozilla/mozjpe)\n\n";
                $message .= sprintf(
                    "Debe se instalada en %s o cambiar el path en el archivo %s",
                    $this->mozjpeg_path,
                    __FILE__
                );
                throw new \Exception($message);
            }

            //Inicializamos en el entorno a partir de las opciones que se pasan por linea de comandos
            $this->setUpEnvironment($input);

            //El origen puede ser un fichero o un directorio con imagenes
            if (is_file($this->source)) {
                $this->optimizeFile($output, $this->source);
            } elseif (is_dir($this->source)) {
                if ($handle = opendir($this->source)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    while (false !== ($entry = readdir($handle))) {
                        if ($entry != "." && $entry != ".." && !is_dir($entry)) {
                            if (strpos(finfo_file($finfo, $entry), 'image') !== false) {
                                $this->optimizeFile($output, $entry);
                            }
                        }
                    }
                    closedir($handle);
                }
            }

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
        //Origen, si no se proporciona se usa el directorio actual
        $source = $input->getArgument('source');
        if (!$source) {
            $source = getcwd();
        }
        $this->source = $source;

        //Directorio de destino
        $dest = $this->getDestinationDirectory($input->getOption('d'));
        if (!$dest) {
            throw new \Exception('Invalid destination directory');
        }
        $this->destination_dir = $dest;


        //Calidad de la recodificacion del fichero (si se pasa)
        $encode_quality = $input->getOption('q');
        $this->encode_quality = is_numeric($encode_quality) ? (int)$encode_quality : null;

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
    private function optimizeFile(OutputInterface $output, $source)
    {
        if (!is_file($source)) {
            {
                throw new \Exception('Invalid source file');
            }
        }
        $source_size = filesize($source);
        $pathinfo = pathinfo($source);
        $dest_file = $this->destination_dir . '/' . $pathinfo['filename'] . '.' . $pathinfo['extension'];

        if ($this->encode_quality) {
            exec(
                sprintf(
                    '%s -quality %d -outfile /tmp/mozjpeg_tmp.jpg %s',
                    $this->getCjpegPath(),
                    $this->encode_quality,
                    $source
                )
            );
        } else {
            copy($source, '/tmp/mozjpeg_tmp.jpg');
        }
        if (!is_file('/tmp/mozjpeg_tmp.jpg')) {
            {
                throw new \Exception('Invalid file');
            }
        }
        exec(sprintf("%s -copy none %s > %s", $this->getJpegTranPath(), '/tmp/mozjpeg_tmp.jpg', $dest_file));
        unlink('/tmp/mozjpeg_tmp.jpg');
        $dest_size = filesize($dest_file);
        $output->writeln(
            sprintf(
                'Optimizando imagen "%s"%s: %s --> %s',
                $source,
                ($this->encode_quality) ? ' (' . (int)$this->encode_quality . '%)' : '',
                $this->humanFilesize($source_size),
                $this->humanFilesize($dest_size)
            )
        );
        $this->optimized_images[] = $source_size - $dest_size;
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

    private function printStats(OutputInterface $output, $quality)
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
                    "Se han optimizado %d archivos%s con una ganancia de %s",
                    count($this->optimized_images),
                    is_numeric($quality) ? '(calidad ' . (int)$quality . '%)' : '',
                    $this->humanFilesize($gain)
                )
            );
        }


    }

}
