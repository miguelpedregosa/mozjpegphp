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


    protected function configure()
    {
        $this->setName('optimize')
            ->setDescription('Optimiza un archivo jpeg usando la librería MozJpeg')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $source = $input->getArgument('source');
            $dest = $input->getArgument('dest');

            if (!is_file($source)) {
                {
                    throw new \Exception('Invalid source file');
                }
            }
            $source_size = filesize($source);
            if (!$dest) {
                $pathinfo = pathinfo($source);
                $dest = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '_moz.' . $pathinfo['extension'];
            }
            $quality = $input->getOption('q');
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
            exec(sprintf("%s -copy none %s > %s", $this->moz_jpeg, '/tmp/mozjpeg_tmp.jpg', $dest));
            unlink('/tmp/mozjpeg_tmp.jpg');
            $dest_size = filesize($dest);
            $output->writeln(
                sprintf(
                    'Optimización completada: %s --> %s',
                    $this->humanFilesize($source_size),
                    $this->humanFilesize($dest_size)
                )
            );
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }
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
