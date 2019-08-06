<?php namespace Pivotal\Survey\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pivotal\Survey\Models\SurveyInterface;

class SurveyResponseRelation extends Relation
{

    /**
     * Create a new relation instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @return void
     */
    public function __construct(Builder $query, SurveyInterface $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();
        $this->addConstraints();
    }


    public function addEagerConstraints(array $models)
    {
        parent::addEagerConstraints($models);
    }

    public function initRelation(array $models, $relation)
    {
        dd('initRelation');
    }

    public function addConstraints()
    {

    }

    public function match(array $models, Collection $results, $relation)
    {

    }

    public function getResults()
    {

        $results = $this->query->get();
        foreach($results as $result)
        {
            $result->sid = $this->parent->sid;
        }
        return $results;
    }

}