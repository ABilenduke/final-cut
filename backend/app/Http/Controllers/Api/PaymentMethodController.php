<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

class PaymentMethodController extends Controller
{
    public function __construct(private StripeService $stripe) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $customerId = $this->getOrCreateStripeCustomer($user);
            $methods = $this->stripe->listPaymentMethods($customerId);

            return $this->successResponse(
                collect($methods)->map(fn ($pm) => [
                    'id' => $pm->id,
                    'brand' => $pm->card->brand,
                    'last4' => $pm->card->last4,
                    'expMonth' => $pm->card->exp_month,
                    'expYear' => $pm->card->exp_year,
                ])->values()
            );
        } catch (InvalidRequestException $e) {
            return $this->errorResponse([['field' => 'stripe', 'message' => $e->getMessage()]], 400);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->errorResponse([['field' => 'stripe', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $customerId = $this->getOrCreateStripeCustomer($user);
            $setupIntent = $this->stripe->createSetupIntent($customerId);

            return $this->successResponse([
                'clientSecret' => $setupIntent->client_secret,
            ]);
        } catch (InvalidRequestException $e) {
            return $this->errorResponse([['field' => 'stripe', 'message' => $e->getMessage()]], 400);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->errorResponse([['field' => 'stripe', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
        }
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->stripe_customer_id) {
            return $this->errorResponse([['message' => 'No payment methods']], 403);
        }

        try {
            $pm = $this->stripe->retrievePaymentMethod($id);

            if ($pm->customer !== $user->stripe_customer_id) {
                return $this->errorResponse([['message' => 'Unauthorized']], 403);
            }

            $this->stripe->detachPaymentMethod($id);

            return $this->successResponse(['success' => true]);
        } catch (InvalidRequestException $e) {
            return $this->errorResponse([['field' => 'stripe', 'message' => $e->getMessage()]], 400);
        } catch (ApiErrorException $e) {
            report($e);

            return $this->errorResponse([['field' => 'stripe', 'message' => 'Payment service is temporarily unavailable. Please try again.']], 502);
        }
    }

    private function getOrCreateStripeCustomer(User $user): string
    {
        if ($user->stripe_customer_id) {
            return $user->stripe_customer_id;
        }

        // Lock the user row to prevent concurrent requests from creating duplicate Stripe customers
        return DB::transaction(function () use ($user) {
            $locked = User::lockForUpdate()->find($user->id);

            // Re-check after acquiring lock — another request may have created the customer
            if ($locked->stripe_customer_id) {
                return $locked->stripe_customer_id;
            }

            $customer = $this->stripe->getOrCreateCustomer($locked->email);
            $locked->update(['stripe_customer_id' => $customer->id]);

            // Sync the in-memory model so subsequent calls in this request see the new ID
            $user->stripe_customer_id = $customer->id;

            return $customer->id;
        });
    }
}
