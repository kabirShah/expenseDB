<?php

namespace App\Services;

class SplitCalculatorService
{
    /**
     * Compute shares for participants.
     *
     * @param  float $totalAmount  Total expense (decimal)
     * @param  array $participants Array of participant identifiers (member_id)
     * @param  string $type        'equal'|'custom'|'weight'
     * @param  array|null $options Optional:
     *                 if 'custom' -> ['custom_shares' => [member_id => amount, ...]]
     *                 if 'weight' -> ['weights' => [member_id => weight, ...]]
     * @return array Returns array of shares: [ member_id => share_amount (float, 2dp) ]
     */
    public function computeShares(float $totalAmount, array $participants, string $type = 'equal', ?array $options = null): array
    {
        // Work with integer cents to avoid float rounding
        $totalCents = (int) round($totalAmount * 100);

        // Normalize participants to unique list of ids
        $participants = array_values(array_unique($participants));
        $count = count($participants);
        if ($count === 0) {
            return [];
        }

        $shares = array_fill_keys($participants, 0);

        if ($type === 'equal') {
            $per = intdiv($totalCents, $count); // floor division
            $remainder = $totalCents - ($per * $count);

            foreach ($participants as $i => $memberId) {
                $assign = $per;
                // distribute remainder 1 cent to first N participants
                if ($i < $remainder) $assign += 1;
                $shares[$memberId] = $assign / 100.0;
            }

            return $shares;
        }

        if ($type === 'custom') {
            $custom = $options['custom_shares'] ?? [];
            // Validate provided total roughly equals totalAmount (within 1 cent)
            $sum = 0;
            foreach ($participants as $memberId) {
                $val = isset($custom[$memberId]) ? (int) round($custom[$memberId] * 100) : 0;
                $shares[$memberId] = $val / 100.0;
                $sum += $val;
            }

            // If custom sum differs by cents, adjust first participant
            $diff = $totalCents - $sum;
            if ($diff !== 0 && !empty($participants)) {
                $first = $participants[0];
                $shares[$first] = (($shares[$first] * 100) + $diff) / 100.0;
            }

            return $shares;
        }

        if ($type === 'weight') {
            $weights = $options['weights'] ?? [];
            $weightsNormalized = [];
            $totalWeight = 0;
            foreach ($participants as $memberId) {
                $w = isset($weights[$memberId]) ? max(0, (float)$weights[$memberId]) : 0.0;
                $weightsNormalized[$memberId] = $w;
                $totalWeight += $w;
            }
            if ($totalWeight <= 0) {
                // fallback to equal
                return $this->computeShares($totalAmount, $participants, 'equal');
            }

            // allocate cents proportional to weight
            $allocated = 0;
            $sharesCents = [];
            foreach ($participants as $memberId) {
                $cents = (int) floor(($weightsNormalized[$memberId] / $totalWeight) * $totalCents);
                $sharesCents[$memberId] = $cents;
                $allocated += $cents;
            }

            // handle remainder cents
            $remainder = $totalCents - $allocated;
            // sort participants by descending fractional remainder to distribute fairly
            $remainders = [];
            foreach ($participants as $memberId) {
                $exact = ($weightsNormalized[$memberId] / $totalWeight) * $totalCents;
                $frac = $exact - floor($exact);
                $remainders[$memberId] = $frac;
            }
            arsort($remainders);
            $keys = array_keys($remainders);
            for ($i = 0; $i < $remainder; $i++) {
                $sharesCents[$keys[$i % count($keys)]]++;
            }

            // convert back to floats
            foreach ($sharesCents as $memberId => $cents) {
                $shares[$memberId] = $cents / 100.0;
            }

            return $shares;
        }

        // default fallback to equal
        return $this->computeShares($totalAmount, $participants, 'equal');
    }

    /**
     * Compute net balances for each participant.
     * net = amount_paid - share_amount (positive => is owed; negative => owes)
     *
     * @param  array $contributions [ member_id => amount_paid (float) ]
     * @param  array $shares        [ member_id => share_amount (float) ]
     * @return array [ member_id => net_amount (float) ]
     */
    public function computeNetBalances(array $contributions, array $shares): array
    {
        $members = array_unique(array_merge(array_keys($contributions), array_keys($shares)));
        $nets = [];
        foreach ($members as $m) {
            $paid = isset($contributions[$m]) ? (float)$contributions[$m] : 0.0;
            $share = isset($shares[$m]) ? (float)$shares[$m] : 0.0;
            // round to 2dp
            $nets[$m] = round($paid - $share, 2);
        }
        return $nets;
    }

    /**
     * Minimize number of transactions. Greedy algorithm:
     *   creditors (net > 0) receive, debtors (net < 0) pay.
     * Returns list of transfers: [ ['from' => id, 'to' => id, 'amount' => float], ... ]
     *
     * @param  array $nets [ member_id => net_amount (float) ]
     * @return array
     */
    public function minimizeTransactions(array $nets): array
    {
        // Convert to arrays of creditors and debtors
        $creditors = [];
        $debtors = [];

        foreach ($nets as $memberId => $net) {
            $amt = round((float)$net, 2);
            if ($amt > 0.005) {
                $creditors[] = ['member_id' => $memberId, 'amount' => $amt];
            } elseif ($amt < -0.005) {
                $debtors[] = ['member_id' => $memberId, 'amount' => -$amt]; // store as positive owed amount
            }
        }

        // Sort creditors desc by amount, debtors desc by amount
        usort($creditors, function ($a, $b) { return $b['amount'] <=> $a['amount']; });
        usort($debtors, function ($a, $b) { return $b['amount'] <=> $a['amount']; });

        $i = 0; $j = 0;
        $transfers = [];

        while ($i < count($debtors) && $j < count($creditors)) {
            $debtor = &$debtors[$i];
            $creditor = &$creditors[$j];

            $transfer = min($debtor['amount'], $creditor['amount']);
            $transfer = round($transfer, 2);

            if ($transfer > 0.00) {
                $transfers[] = [
                    'from_member_id' => $debtor['member_id'],
                    'to_member_id' => $creditor['member_id'],
                    'amount' => $transfer
                ];

                // subtract
                $debtor['amount'] = round($debtor['amount'] - $transfer, 2);
                $creditor['amount'] = round($creditor['amount'] - $transfer, 2);
            }

            // increment pointers if zero
            if ($debtor['amount'] <= 0.005) $i++;
            if ($creditor['amount'] <= 0.005) $j++;
        }

        return $transfers;
    }
}
