<?php
namespace App\Policies;
use App\Models\Order;
use App\Models\User;
class OrderPolicy {
    public function view(User $user, Order $order): bool {
        if ($user->hasRole('Customer')) return $order->user_id === $user->id;
        if ($user->hasAnyRole(['Super Admin','Owner'])) return true;
        return $user->hasAnyRole(['Admin EPUL','Admin RAPLY','Finance','Produksi']) && (int)$user->branch_id === (int)$order->branch_id;
    }
    public function update(User $user, Order $order): bool { return $this->view($user,$order) && ! $user->hasRole('Customer'); }
    public function downloadDesign(User $user, Order $order): bool { return $this->view($user,$order); }
}
