<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use App\Utils\BaminiConverter;
use App\Models\Product;

class BarcodePrintController extends Controller
{
    private function winQuote(string $value): string
    {
        // Quote for cmd.exe. Double quotes inside must be doubled.
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private function startDetachedProcess(string $commandLine): bool
    {
        /**
         * Launch BarTender on Windows in a detached way using cmd.exe
         */
        if (!function_exists('exec')) {
            Log::error('PHP exec() is disabled. Cannot launch BarTender.', [
                'command' => $commandLine,
            ]);
            return false;
        }

        $output    = [];
        $returnVar = 0;

        // Build full command for logging
        $fullCommand = 'cmd /c start "" ' . $commandLine;

        @exec($fullCommand, $output, $returnVar);

        Log::info('BarTender process launched', [
            'exec_command' => $fullCommand,
            'return_code'  => $returnVar,
            'output'       => $output,
            'exec_enabled' => function_exists('exec'),
        ]);

        return $returnVar === 0;
    }

    /*
     * This method generates BarTender template and prints labels
     */
    public function print(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.prod_code' => ['required', 'string', 'max:50'],
            'items.*.barcode' => ['required', 'string', 'max:100'],
            'items.*.prod_name' => ['required', 'string', 'max:255'],
            'items.*.selling_price' => ['nullable', 'numeric'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.type' => ['nullable', 'string', 'max:50'],
        ]);

        // BTW template path is mandatory for BarTender-based printing
        $btwTemplatePath = env('BARCODE_BTW_TEMPLATE')
            ?: env('BARTENDER_TEMPLATE_PATH', 'C:\\barcode\\STIC33X21.btw');

        // Data file location (used by the BTW template)
        $outputDir = env('BARCODE_OUTPUT_DIR', 'C:\\barcode');
        $dataFileName = env('BARCODE_OUTPUT_FILE') ?: env('BARCODE_DATA_FILE', 'venpaa_barcode.txt');
        $dataFilePath = rtrim($outputDir, "\\/") . DIRECTORY_SEPARATOR . $dataFileName;

        $useBartender = filter_var(env('USE_BARTENDER', true), FILTER_VALIDATE_BOOL);

        try {
            // Create output directory if it doesn't exist
            if (!File::exists($outputDir)) {
                File::makeDirectory($outputDir, 0777, true);
            }

            $processedItems = [];
            $totalLabels = 0;

            foreach ($data['items'] as $item) {
                $type = $item['type'] ?? 'DEFAULT';
                $price = (float)($item['selling_price'] ?? 0);
                $qty = (int)$item['qty'];
                $totalLabels += $qty;

                $processedItems[] = [
                    'prod_code' => $item['prod_code'],
                    'barcode' => $item['barcode'] ?? $item['prod_code'],
                    'prod_name' => $item['prod_name'],
                    'selling_price' => number_format($price, 2, '.', ''),
                    'qty' => $qty,
                    'type' => $type,
                ];
            }

            // Generate text file with comma-separated values for BarTender
            if ($useBartender) {
                $lines = [];
                foreach ($processedItems as $item) {
                    for ($i = 0; $i < $item['qty']; $i++) {
                        // Truncate product name to max 20 characters for the data file
                        $name = $item['prod_name'] ?? '';
                        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                            if (mb_strlen($name) > 20) {
                                $name = mb_substr($name, 0, 20) . '...';
                            }
                        } else {
                            if (strlen($name) > 20) {
                                $name = substr($name, 0, 20) . '...';
                            }
                        }

                        $row = [
                            $item['prod_code'],
                            $item['barcode'],
                            $name,
                            $item['selling_price'],
                            $item['type'],
                        ];
                        $lines[] = implode(',', $row);
                    }
                }

                // Write text file without header
                File::put($dataFilePath, implode(PHP_EOL, $lines) . PHP_EOL);

                Log::info('Barcode data file created', [
                    'file' => $dataFilePath,
                    'total_labels' => $totalLabels,
                    'lines' => count($lines)
                ]);

                // Ensure BTW template exists
                if (!File::exists($btwTemplatePath)) {
                    $errors = [
                        "BarTender template not found at: {$btwTemplatePath}",
                    ];

                    Log::warning('BarTender template not found', $errors);

                    return response()->json([
                        'success' => false,
                        'message' => 'BarTender template configuration error.',
                        'errors' => $errors,
                        'items' => $processedItems,
                        'total_qty' => $totalLabels,
                        'data_file' => $dataFilePath,
                    ], 500);
                }

                // Verify data file was created
                if (!File::exists($dataFilePath)) {
                    Log::error('Data file not found after creation', [
                        'expected_path' => $dataFilePath
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Data file creation failed.',
                        'error' => "Data file not found at: {$dataFilePath}"
                    ], 500);
                }

                // Log all paths for debugging
                Log::info('Opening BarTender template directly', [
                    'template_file' => $btwTemplatePath,
                    'data_file' => $dataFilePath,
                    'data_file_exists' => File::exists($dataFilePath),
                    'data_file_size' => File::size($dataFilePath) . ' bytes'
                ]);

                // Just open the BTW file
                $commandLine = $this->winQuote($btwTemplatePath);

                Log::info('Launching BarTender via template association', [
                    'command' => $commandLine,
                ]);

                $launched = $this->startDetachedProcess($commandLine);

                if (!$launched) {
                    Log::error('Failed to open BarTender template directly', [
                        'command' => $commandLine,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to open BarTender template. Please contact system administrator.',
                        'items' => $processedItems,
                        'total_qty' => $totalLabels,
                        'data_file' => $dataFilePath,
                        'method' => 'bartender',
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => "BarTender template opened successfully. Please confirm printing from BarTender. Total labels: {$totalLabels}",
                    'items' => $processedItems,
                    'total_qty' => $totalLabels,
                    'data_file' => $dataFilePath,
                    'method' => 'bartender',
                ], 200);
            } else {
                // BarTender disabled, return data for browser printing
                return response()->json([
                    'success' => true,
                    'message' => "Successfully prepared data for browser printing.",
                    'items' => $processedItems,
                    'total_qty' => $totalLabels,
                    'method' => 'browser'
                ], 200);
            }
        } catch (\Throwable $e) {
            Log::error('Barcode print error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare barcode data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
