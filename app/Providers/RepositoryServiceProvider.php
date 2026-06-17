<?php

namespace App\Providers;

use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CourtRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\MembershipRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\CourtRepository;
use App\Repositories\Eloquent\CustomerRepository;
use App\Repositories\Eloquent\MembershipRepository;
use App\Repositories\Eloquent\PaymentRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Contract → Concrete bindings.
     */
    public array $bindings = [
        BookingRepositoryInterface::class    => BookingRepository::class,
        CourtRepositoryInterface::class      => CourtRepository::class,
        PaymentRepositoryInterface::class    => PaymentRepository::class,
        MembershipRepositoryInterface::class => MembershipRepository::class,
        CustomerRepositoryInterface::class   => CustomerRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $contract => $concrete) {
            $this->app->bind($contract, $concrete);
        }
    }

    public function provides(): array
    {
        return array_keys($this->bindings);
    }
}
