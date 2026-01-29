<?php

namespace App\Http\Controllers\Master;

use Illuminate\Http\Request;  
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use App\Models\ClientBarcodeSetting;

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
         * Launch BarTender. Note: This only works if PHP is running on the same Windows machine
         * or if a remote execution bridge is configured.
         */
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            Log::warning('Linux server detected. Cannot launch local Windows commands directly.', [
                'command' => $commandLine,
                'note' => 'Ensure client Windows PCs are monitoring the shared barcode folder for new data files.'
            ]);
            // If on Linux, we return true if we reached this point, assuming the file write was the goal
            // In a real production setup, you would trigger a client-side print agent here.
            return true;
        }

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

        Log::info('BarTender process launched on Windows', [
            'exec_command' => $fullCommand,
            'return_code'  => $returnVar,
        ]);

        return $returnVar === 0;
    }

    private function getMacAddress($ip)
    {
        // For localhost, return a placeholder
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'LOCAL_HOST';
        }

        $output = [];
        $mac = null;

        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            // Linux server: Use arp -an to find MAC of the client IP
            @exec("arp -an " . escapeshellarg($ip), $output);
            foreach ($output as $line) {
                if (preg_match('/([a-fA-F0-9]{2}[:\-]){5}[a-fA-F0-9]{2}/', $line, $matches)) {
                    $mac = strtoupper($matches[0]);
                    break;
                }
            }
        } else {
            // Windows server (local dev): Use arp -a
            @exec("arp -a " . escapeshellarg($ip), $output);
            foreach ($output as $line) {
                if (preg_match('/([a-fA-F0-9]{2}[:\-]){5}[a-fA-F0-9]{2}/', $line, $matches)) {
                    $mac = strtoupper(str_replace('-', ':', $matches[0]));
                    break;
                }
            }
        }

        return $mac ?: $ip; // Fallback to IP if MAC lookup fails
    }

    /*
     * This method generates BarTender template and prints labels
     */
    public function print(Request $request)
    {
        $clientIp = $request->ip();
        $physicalAddress = $this->getMacAddress($clientIp);
        
        Log::info("Barcode print request", [
            'ip' => $clientIp,
            'mac' => $physicalAddress
        ]);

        // Auto-register or fetch settings using Physical Address (stored in ip_address column)
        $clientSettings = ClientBarcodeSetting::firstOrCreate(
            ['ip_address' => $physicalAddress],
            [
                'template_path' => env('BARCODE_BTW_TEMPLATE') ?: 'C:\\barcode\\STIC33X21.btw',
                'output_dir' => env('BARCODE_OUTPUT_DIR', 'C:\\barcode'),
                'data_file_name' => env('BARCODE_OUTPUT_FILE') ?: 'venpaa_barcode.txt',
                'is_active' => true
            ]
        );

        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.prod_code' => ['required', 'string', 'max:50'],
            'items.*.barcode' => ['required', 'string', 'max:100'],
            'items.*.prod_name' => ['required', 'string', 'max:255'],
            'items.*.selling_price' => ['nullable', 'numeric'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.type' => ['nullable', 'string', 'max:50'],
        ]);

        // BTW template path - specific to this machine
        $btwTemplatePath = $clientSettings->template_path;

        // Data file location
        $outputDir = $clientSettings->output_dir;
        $dataFileName = $clientSettings->data_file_name;
        
        // If we are on Linux, we might need to dynamically inject the IP into the path 
        // if the output_dir uses a template like \\{IP}\C$\barcode
        $finalOutputDir = str_replace('{IP}', $clientIp, $outputDir);
        $dataFilePath = rtrim($finalOutputDir, "\\/") . DIRECTORY_SEPARATOR . $dataFileName;

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
