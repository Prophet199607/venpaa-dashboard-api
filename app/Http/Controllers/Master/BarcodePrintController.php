<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class BarcodePrintController extends Controller
{
    /*
     * Send barcode print data to local C# BarTender agent
     */
    public function print(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.prod_code' => ['required', 'string', 'max:50'],
            'items.*.prod_name' => ['required', 'string', 'max:255'],
            'items.*.selling_price' => ['required', 'numeric'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

       $agentUrl = 'http://192.168.1.9:9000/print';
        // $agentUrl = 'http://127.0.0.1:9000/print';
        
        $totalLabels = 0;

        try {
            $payload = [];
            foreach ($data['items'] as $item) {
                $payload[] = [
                    'Code'  => $item['prod_code'],
                    'Name'  => $item['prod_name'],
                    'Price' => (float) $item['selling_price'],
                    'Qty'   => (int) $item['qty'],
                ];

                $totalLabels += (int) $item['qty'];
            }


            $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->timeout(5)
                    ->post($agentUrl, $payload);

                if (!$response->successful()) {
                    Log::error('BarTender Agent error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'BarTender Agent rejected the request',
                        'status' => $response->status(),
                    ], 500);
                }

            return response()->json([
                'success' => true,
                'message' => "Print request sent successfully",
                'total_labels' => $totalLabels,
                'method' => 'bartender_agent',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Barcode print exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send print request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
