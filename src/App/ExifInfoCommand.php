<?php
/**
 *
 * User: migue
 * Date: 1/03/15
 * Time: 19:45
 */

namespace MozJpegPhp\App;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExifInfoCommand extends Command
{
    protected function configure()
    {
        $this->setName('exif')
            ->setDescription('Shows EXIF info for a JPEG image')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'JPEG File'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $source = $input->getArgument('source');
            if (!is_file($source) || !$this->isJpegImage($source)) {
                throw new \Exception(sprintf('Invalid jpeg file %s', $source));
            }
            $exif = exif_read_data($source);

            $formatter = $this->getHelper('formatter');
            $formattedLine = $formatter->formatSection(
                'Exif data',
                $source
            );
            $output->writeln($formattedLine);

            $table = new Table($output);
            $table->setHeaders(array('Name', 'Value'));
            foreach ($exif as $clave => $sección) {
                if (is_array($sección)) {
                    foreach ($sección as $name => $value) {
                        $table->addRow(array($name, $value));

                    }
                } else {
                    $table->addRow(array($clave, $sección));
                }

            }
            $table->render();

        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return 1;
        }
        return 0;
    }

    private function isJpegImage($source)
    {
        return exif_imagetype($source) === IMAGETYPE_JPEG;
    }
}
