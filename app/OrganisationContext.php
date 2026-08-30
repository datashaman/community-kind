<?php

namespace App;

use App\Models\Organisation;
use Closure;
use Illuminate\Support\Facades\Context;
use LogicException;

class OrganisationContext
{
    private const ID = 'organisation_id';

    private const ACCESS_VERSION = 'organisation_access_version';

    public function id(): int
    {
        $id = Context::get(self::ID);

        if (! is_int($id)) {
            throw new LogicException('An Organisation context is required.');
        }

        return $id;
    }

    public function organisation(): Organisation
    {
        $organisation = Organisation::query()->find($this->id());
        $accessVersion = Context::getHidden(self::ACCESS_VERSION);

        if ($organisation === null || ! is_int($accessVersion) || $organisation->access_version !== $accessVersion) {
            throw new LogicException('The Organisation context is stale or unavailable.');
        }

        return $organisation;
    }

    public function ensureOwns(int $organisationId): void
    {
        if ($this->organisation()->id !== $organisationId) {
            throw new LogicException('The record does not belong to the current Organisation context.');
        }
    }

    public function run(Organisation $organisation, Closure $callback): mixed
    {
        $organisation = Organisation::query()->find($organisation->id);

        if ($organisation === null) {
            throw new LogicException('The Organisation context is unavailable.');
        }

        return Context::scope(
            $callback,
            [self::ID => $organisation->id],
            [self::ACCESS_VERSION => $organisation->access_version],
        );
    }

    public function each(Closure $callback): void
    {
        Organisation::query()->lazyById()->each(
            fn (Organisation $organisation) => $this->run(
                $organisation,
                fn () => $callback($organisation),
            ),
        );
    }
}
