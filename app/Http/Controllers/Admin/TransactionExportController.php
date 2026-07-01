<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TransactionExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $type = (string) $request->query('type', 'all');
        $search = (string) $request->query('search', '');

        $query = Transaction::query()->with('player');
        if ($type !== 'all' && $type !== '') {
            $query->where('type', $type);
        }
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('telegram_id', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhereHas('player', fn ($p) => $p->where('name', 'like', $term));
            });
        }

        $filename = 'transactions-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'telegram_id', 'player', 'type', 'status', 'amount', 'balance_after', 'provider', 'reference', 'round_id', 'created_at']);

            $query->latest('id')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $t) {
                    fputcsv($out, array_map($this->csvSafe(...), [
                        $t->id,
                        $t->telegram_id,
                        $t->player?->name,
                        $t->type->value,
                        $t->status->value,
                        $t->amount,
                        $t->balance_after,
                        $t->provider,
                        $t->reference,
                        $t->round_id,
                        $t->created_at?->toIso8601String(),
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralise CSV formula injection: a spreadsheet treats a cell starting
     * with = + - @ (or a leading tab/CR) as a formula. Player names and payment
     * references are user-controlled, so prefix such values with a single quote.
     */
    private function csvSafe(int|string|null $value): int|string|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
