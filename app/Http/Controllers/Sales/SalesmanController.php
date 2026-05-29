<?php

namespace App\Http\Controllers\Sales;

use App\Models\DocNumber;
use App\Models\RepSalesman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SalesmanController extends Controller
{
    public function index()
    {
        try {
            $salesmen = RepSalesman::all();
            return response()->json([
                'success' => true,
                'message' => 'Salesmen fetched successfully',
                'data' => $salesmen
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch salesmens',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateSalesmanCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Salesman')->first()->getDocCode();

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $docCode['code']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sales_code' => 'required|string|max:255',
            'sales_name' => 'required|string|max:255',
            'sales_email' => 'nullable|email|max:255',
            'sales_phone' => 'nullable|string|max:20',
            'sales_address' => 'nullable|string|max:255',
            'sales_status' => 'required|integer|in:0,1',
        ]);

        try {
            DB::beginTransaction();

            // Check if Salesman code already exists
            if (RepSalesman::where('sales_code', $data['sales_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Salesman')->first()->getDocCode();
                $data['sales_code'] = $docCode['code'];
            }

            $salesman = RepSalesman::create($data);
            DocNumber::where('type', 'Salesman')->first()->incrementLastId();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Salesman created successfully',
                'salesman' => $salesman
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create salesman', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($sales_code)
    {
        try {
            $salesman = RepSalesman::where('sales_code', $sales_code)->first();
            return response()->json([
                'success' => true,
                'message' => 'Salesman fetched successfully',
                'data' => $salesman
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Salesman not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $salesman = RepSalesman::findOrFail($id);

        $validatedData = $request->validate([
            'sales_name' => 'required|string|max:255',
            'sales_email' => 'nullable|email|max:255',
            'sales_phone' => 'nullable|string|max:20',
            'sales_address' => 'nullable|string|max:255',
            'sales_status' => 'required|integer|in:0,1',
        ]);

        $salesman->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Salesman updated successfully',
            'salesman' => $salesman
        ]);
    }
}
