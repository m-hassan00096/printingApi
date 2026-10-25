<?php
namespace Modules\Customers\Http\Controllers;

use App\Enums\HttpStatusCode;
use App\Helpers\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Customers\Actions\CreateCustomerAction;
use Modules\Customers\Actions\DeleteCustomerAction;
use Modules\Customers\Actions\UpdateCustomerAction;
use Modules\Customers\Http\Requests\IndexCustomersRequest;
use Modules\Customers\Http\Requests\StoreCustomerRequest;
use Modules\Customers\Http\Requests\UpdateCustomerRequest;
use Modules\Customers\Http\Resources\CustomerCollection;
use Modules\Customers\Http\Resources\CustomerResource;
use Modules\Customers\Models\Customer;
use Modules\Customers\Services\CustomerQueryService;
use Modules\Settings\Services\Selects\ParameterValueSelectService;

class CustomerController extends Controller
{
    public function index(IndexCustomersRequest $request, CustomerQueryService $customers): JsonResponse
    {
        return ApiResponse::success(
            data: (new CustomerCollection($customers->paginate($request)))->resolve($request),
        );
    }

    public function customerTypes(ParameterValueSelectService $types): JsonResponse
    {
        return ApiResponse::success(data: ['customerTypes' => $types->getCustomerTypes()]);
    }

    public function show(Customer $customer): JsonResponse
    {
        return ApiResponse::success(
            data: (new CustomerResource($customer->load('customerType')))->resolve(),
        );
    }

    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): JsonResponse
    {
        $customer = $action->execute($request->validated());

        return ApiResponse::success(
            data: (new CustomerResource($customer->load('customerType')))->resolve($request),
            message: __('customers.messages.created'),
            status: HttpStatusCode::CREATED,
        );
    }

    public function update(UpdateCustomerRequest $request, Customer $customer, UpdateCustomerAction $action): JsonResponse
    {
        $action->execute($customer, $request->validated());

        return ApiResponse::success(message: __('customers.messages.updated'));
    }

    public function destroy(Customer $customer, DeleteCustomerAction $action): JsonResponse
    {
        $action->execute($customer);

        return ApiResponse::success();
    }
}
