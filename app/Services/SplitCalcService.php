<?php
namespace App\Services;

class SplitCalcService
{
    public function calculate($type, $total, $participants)
    {
        switch ($type) {
            case 'equal':
                return $this->equalSplit($total, $participants);

            case 'custom':
                return $this->customSplit($participants);

            case 'weight':
                return $this->weightSplit($total, $participants);
        }
    }

    private function equalSplit($total, $participants)
    {
        $count = count($participants);
        $share = round($total / $count, 2);

        return array_map(fn($id) => [
            'member_id' => $id,
            'share' => $share
        ], $participants);
    }

    private function customSplit($participants)
    {
        return array_map(fn($p) => [
            'member_id' => $p['member_id'],
            'share' => $p['share']
        ], $participants);
    }

    private function weightSplit($total, $participants)
    {
        $totalWeight = array_sum(array_column($participants, 'weight'));

        return array_map(function ($p) use ($total, $totalWeight) {
            $share = ($p['weight'] / $totalWeight) * $total;
            return [
                'member_id' => $p['member_id'],
                'share' => round($share, 2)
            ];
        }, $participants);
    }
}
