<?php
namespace Backpack\Profile\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Backpack\Profile\app\Models\WithdrawalRequest;
use Backpack\Profile\app\Services\WithdrawalService;

class WithdrawalController extends Controller
{
    public function __construct(protected WithdrawalService $service) {}

    public function index(Request $r)
    {
        return WithdrawalRequest::query()
            ->where('user_id', $r->user()->id)
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'amount'          => ['required','numeric','min:0.01'],
            'payout_currency' => ['required','string','max:16'], // ← теперь это валюта, в которой хочет получить пользователь
            'method'          => ['nullable','string','max:64'],
            'details'         => ['array'],
        ]);

        $wr = $this->service->create(
            $r->user()->id,
            (float)$data['amount'],
            $data['payout_currency'],
            $data['method'] ?? null,
            $data['details'] ?? []
        );

        return response()->json($wr, 201);
    }

    public function cancel(Request $r, int $id)
    {
        $wr = WithdrawalRequest::query()
            ->where('id', $id)
            ->where('user_id', $r->user()->id)
            ->firstOrFail();

        $this->service->reject($wr->id, $r->user()->id, 'user_cancel');
        return ['ok' => true];
    }
}
