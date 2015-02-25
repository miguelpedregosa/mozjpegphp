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
    private $moz_jpeg = '/opt/mozjpeg/bin/jpegtran';
    private $cjpeg = '/opt/mozjpeg/bin/cjpeg';

    private $optimized_images = array();

    protected function configure()
    {
        $this->setName('optimize')
            ->setDescription('Optimiza un archivo jpeg usando la librería MozJpeg')
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $source = $input->getArgument('source');
            $quality = $input->getOption('q');
            $dest = $this->getDestinationDirectory($input->getOption('d'), $quality);

            //Origen (si no se pasa, usa el directorio actual)
            if (!$source) {
                $source = getcwd();
            }

            //Directorio de destino
            if (!$dest) {
                throw new \Exception('Invalid destination directory');
            }
            //El origen puede ser un fichero o un directorio con imagenes
            if (is_file($source)) {
                $this->optimizeFile($output, $source, $dest, $quality);
            } elseif (is_dir($source)) {
                if ($handle = opendir($source)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    while (false !== ($entry = readdir($handle))) {
                        if ($entry != "." && $entry != ".." && !is_dir($entry)) {
                            if (strpos(finfo_file($finfo, $entry), 'image') !== false) {
                                $this->optimizeFile($output, $entry, $dest, $quality);
                            }
                        }
                    }
                    closedir($handle);
                }
            }
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

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
    }

    /**
     * @param null $dest
     * @param null $quality
     * @return bool|null|string
     */
    private function getDestinationDirectory($dest = null, $quality = null)
    {
        //Directorio por defecto
        if (is_null($dest)) {
            $dest = (is_numeric($quality)) ? sprintf('optimized_%d', $quality) : 'optimized';
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
     * @param null $dest
     * @param null $quality
     * @throws \Exception
     */
    private function optimizeFile(OutputInterface $output, $source, $dest = null, $quality = null)
    {
        if (!is_file($source)) {
            {
                throw new \Exception('Invalid source file');
            }
        }
        $source_size = filesize($source);
        $pathinfo = pathinfo($source);
        $dest_file = $dest . '/' . $pathinfo['filename'] . $pathinfo['extension'];

        if (is_numeric($quality)) {
            exec(sprintf('%s -quality %d -outfile /tmp/mozjpeg_tmp.jpg %s', $this->cjpeg, $quality, $source));
        } else {
            copy($source, '/tmp/mozjpeg_tmp.jpg');
        }
        if (!is_file('/tmp/mozjpeg_tmp.jpg')) {
            {
                throw new \Exception('Invalid file');
            }
        }
        exec(sprintf("%s -copy none %s > %s", $this->moz_jpeg, '/tmp/mozjpeg_tmp.jpg', $dest_file));
        unlink('/tmp/mozjpeg_tmp.jpg');
        $dest_size = filesize($dest_file);
        $output->writeln(
            sprintf(
                'Optimizando imagen "%s"%s: %s --> %s',
                $source,
                ($quality) ? ' (' . (int)$quality . '%)' : '',
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

}
