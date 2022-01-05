<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class importManager
{

    private $entityManager;
    private $params;

    public function __construct(entityManager $entityManager, ParameterBagInterface  $params)
    {
        $this->entityManager = $entityManager;
        $this->params = $params;
    }

    public function importFileAction($path, $archive_path, $fileName, $fileNameWithoutExtension, $extension)
    {
        $spreadsheet = null;
        $readerXlsx = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $readerCsv = new \PhpOffice\PhpSpreadsheet\Reader\Csv();

        if ($extension == "xlsx") {
            $spreadsheet = $readerXlsx->load($path . "/" . $fileName);
        }
        if ($extension == "csv") {
            $spreadsheet = $readerCsv->load($path . "/" . $fileName);
        }

        if ($spreadsheet) {
            $worksheet = $spreadsheet->getActiveSheet();
            // Get the highest row and column numbers referenced in the worksheet
            $highestRow = $worksheet->getHighestRow(); // e.g. 10
            $highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn); // e.g. 5

            $headers = $worksheet->rangeToArray(
                'A1:' . $highestColumn . '1',
                null,
                false,
                false,
                true
            );
            $headers = $headers[1];

            for ($row = 2; $row <= $highestRow; ++$row) {
                $extraPayload = [];
                for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                    $value = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                    $extraPayload[$headers[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col)]] = $value;
                }
                $data = $this->entityManager->setResult("References", $fileNameWithoutExtension, $extraPayload);
            }
        }
        rename($path . "/" . $fileName, $archive_path . "/" . $fileName);
    }
}
