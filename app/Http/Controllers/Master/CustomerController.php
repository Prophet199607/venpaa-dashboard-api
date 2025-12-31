<?php

namespace App\Http\Controllers\Master;

use App\Models\Customer;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\CustomerRequest;
use App\Http\Resources\Master\CustomerResource;

class CustomerController extends Controller
{
    public function generateCustomerCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Customer')->first()->getDocCode();

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

    public function index()
    {
        try {
            $customers = Customer::where('is_active', 1)->get();
            return response()->json([
                'success' => true,
                'message' => 'Customers fetched successfully',
                'data' => CustomerResource::collection($customers)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($customerCode)
    {
        try {
            $customer = Customer::where('customer_code', $customerCode)->first();
            return response()->json([
                'success' => true,
                'message' => 'Customer fetched successfully',
                'data' => new CustomerResource($customer)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function search(Request $request)
    {
        try {
            $query = $request->input('query', '');

            $customers = Customer::where('is_active', 1)
                ->where(function ($q) use ($query) {
                    $q->where('customer_name', 'LIKE', "%{$query}%")
                    ->orWhere('customer_code', 'LIKE', "%{$query}%");
                })
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Customers search results',
                'data' => CustomerResource::collection($customers),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(CustomerRequest $request)
    {
        try {
            $data = $request->validated();

            // Check if Customer code already exists
            if (Customer::where('customer_code', $data['customer_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Customer')->first()->getDocCode();
                $data['customer_code'] = $docCode['code'];
            }

            $customer = Customer::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => new CustomerResource($customer)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(CustomerRequest $request, $customerCode)
    {
        try {
            $customer = Customer::where('customer_code', $customerCode)->first();
            $data = $request->validated();

            $customer->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => new CustomerResource($customer)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
