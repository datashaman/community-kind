<?php

namespace App\Concerns;

use App\OrganisationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

trait BelongsToOrganisation
{
    public static function bootBelongsToOrganisation(): void
    {
        static::addGlobalScope('organisation', function (Builder $builder): void {
            $organisation = app(OrganisationContext::class)->organisation();

            $builder->where(
                $builder->getModel()->qualifyColumn('organisation_id'),
                $organisation->id,
            );
        });

        static::creating(function (Model $model): void {
            $organisationId = app(OrganisationContext::class)->id();

            if ($model->getAttribute('organisation_id') === null) {
                $model->setAttribute('organisation_id', $organisationId);
            }

            app(OrganisationContext::class)->ensureOwns((int) $model->getAttribute('organisation_id'));
        });

        static::saving(function (Model $model): void {
            if ($model->exists && $model->isDirty('organisation_id')) {
                throw new LogicException('Organisation ownership is immutable.');
            }

            if ($model->exists) {
                app(OrganisationContext::class)->ensureOwns((int) $model->getOriginal('organisation_id'));
            }
        });

        static::deleting(function (Model $model): void {
            app(OrganisationContext::class)->ensureOwns((int) $model->getAttribute('organisation_id'));
        });
    }
}
