<?php

namespace App\Http\Controllers\Sales;

use App\Models\SecLevel;
use App\Models\Location;
use App\Models\DocNumber;
use App\Models\RepCashier;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CashierController extends Controller
{
    public function index()
    {
        try {
            $cashiers = RepCashier::leftJoin('locations', 'rep_cashiers.cashier_loca', '=', 'locations.loca_code')
                ->select('rep_cashiers.*', 'locations.loca_name')
                ->get();
            return response()->json([
                'success' => true,
                'message' => 'Cashiers fetched successfully',
                'data' => $cashiers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cashiers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($emp_code)
    {
        try {
            $cashier = RepCashier::where('emp_code', $emp_code)->first();
            return response()->json([
                'success' => true,
                'message' => 'Cashier fetched successfully',
                'data' => $cashier
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cashier not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function getFormData()
    {
        $secLevels = SecLevel::all();
        $locations = Location::where("is_active", 1)->get();
        return response()->json([
            'sec_levels' => $secLevels,
            'locations' => $locations
        ]);
    }

    public function generateCashierCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Cashier')->first()->getDocCode();

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
        $validatedData = $request->validate([
            'emp_code' => 'required|string|max:255',
            'emp_name' => 'required|string|max:50',
            'username' => 'required|string|max:50|unique:rep_cashiers',
            'password' => 'required|string|max:50|unique:rep_cashiers',
            'mobile_number' => 'nullable|string|max:20',
            'cashier_loca' => 'required|string',
            'cancel' => 'boolean',
            'refund' => 'boolean',
            'cash_refund' => 'boolean',
            'cash_out' => 'boolean',
            'discount_allow' => 'boolean',
            'discount' => 'numeric',
            'dept_allow' => 'boolean',
            'day_end_rep' => 'boolean',
            'bill_copy' => 'boolean',
            'sec_level' => 'nullable|integer',
            'disables' => 'boolean',
            'cr_note_issue' => 'boolean',
            'gift_voucher_issue' => 'boolean',
            'sale_value' => 'boolean',
            'new_price_allow' => 'boolean',
            'refund_limit' => 'nullable|numeric',
            'discount_amount' => 'numeric',
        ]);

        try {
            DB::beginTransaction();
            
            $data = array_merge($validatedData, [
                'last_mod_user' => auth()->user()->id,
                'last_mod_date' => now(),
                'tr_date' => now(),
                'msrepl_tran_version' => Str::uuid(),
            ]);

            // Map sec_level choice to idx column
            if (isset($validatedData['sec_level'])) {
                $secLevelObj = SecLevel::where('sec_level', $validatedData['sec_level'])->first();
                if ($secLevelObj) {
                    $data['idx'] = $secLevelObj->idx;
                }
            }

            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $data[$key] = $value ? 1 : 0;
                }
            }

            $cashier = RepCashier::create($data);

            // Increment DocNumber last_id
            $docNumber = DocNumber::where('type', 'Cashier')->first();
            if ($docNumber) {
                $docNumber->incrementLastId();
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cashier created successfully',
                'cashier' => $cashier
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create cashier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $cashier = RepCashier::findOrFail($id);

        $validatedData = $request->validate([
            'emp_name' => 'required|string|max:50',
            'username' => 'required|string|max:50|unique:rep_cashiers,username,' . $id,
            'password' => 'required|string|max:50|unique:rep_cashiers,password,' . $id,
            'mobile_number' => 'nullable|string|max:20',
            'cashier_loca' => 'required|string',
            'cancel' => 'boolean',
            'refund' => 'boolean',
            'cash_refund' => 'boolean',
            'cash_out' => 'boolean',
            'discount_allow' => 'boolean',
            'discount' => 'numeric',
            'dept_allow' => 'boolean',
            'day_end_rep' => 'boolean',
            'bill_copy' => 'boolean',
            'sec_level' => 'nullable|integer',
            'disables' => 'boolean',
            'cr_note_issue' => 'boolean',
            'gift_voucher_issue' => 'boolean',
            'sale_value' => 'boolean',
            'new_price_allow' => 'boolean',
            'refund_limit' => 'nullable|numeric',
            'discount_amount' => 'numeric',
        ]);

        try {
            DB::beginTransaction();

            $data = array_merge($validatedData, [
                'last_mod_user' => auth()->user()->id,
                'last_mod_date' => now(),
            ]);

            // Map sec_level choice to idx column
            if (isset($validatedData['sec_level'])) {
                $secLevelObj = SecLevel::where('sec_level', $validatedData['sec_level'])->first();
                if ($secLevelObj) {
                    $data['idx'] = $secLevelObj->idx;
                }
            }

            foreach ($data as $key => $value) {
                if (is_bool($value)) {
                    $data[$key] = $value ? 1 : 0;
                }
            }

            $cashier->update($data);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cashier updated successfully',
                'cashier' => $cashier
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cashier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
