<?php

namespace Lab2view\RepositoryGenerator;

use Illuminate\Support\Facades\Log;

abstract class BaseRepository implements RepositoryInterface
{
    protected $model;

    /**
     * Create a new repository instance.
     *
     * @param \Illuminate\Database\Eloquent\Model|mixed $model
     */
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * @param string $key
     * @param $value
     * @param bool $withTrashed
     * @return bool
     */
    public function exists(string $key, $value, $withTrashed = false)
    {
        try {
            $query = $this->model->where($key, $value);
            if ($withTrashed)
                $query = $query->withTrashed();

            return $query->exists();
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return false;
        }
    }

    /**
     * @param string $attr_name
     * @param $attr_value
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return mixed|null
     */
    public function getByAttribute(string $attr_name, $attr_value, $relations = [], $withTrashed = false, $selects = [])
    {
        try {
            $query = $this->initiateQuery($relations, $withTrashed, $selects);
            return $query->where($attr_name, $attr_value)->first();
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        }
    }

    /**
     * @param int $n
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return mixed
     */
    public function getPaginate(int $n, $relations = [], $withTrashed = false, $selects = [])
    {
        $query = $this->initiateQuery($relations, $withTrashed, $selects);
        return $query->paginate($n);
    }

    /**
     * @param array $inputs
     * @return mixed
     */
    public function store(array $inputs)
    {
        try {
            return $this->model->create($inputs);
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        }
    }

    /**
     * Saf
     * @param array $searchCriterias
     * @param array $newValues
     * @return \Illuminate\Database\Eloquent\Model
     * @throws Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function updateBlank(array $searchCriterias, array $newValues)
    {
        try {
            $model = $this->model->where($searchCriterias)->firstOrFail();

            $blankValues = collect($newValues)->filter(function ($fieldValue, $fieldName) use ($model) {
                return blank($model->getAttribute($fieldName));
            })->toArray();

            $model->fill($blankValues)->save();

            return $model;
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        } catch (\Illuminate\Database\ModelNotFoundException $exc) {
           throw $exc;
        }
    }

    /**
     * @param $id
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return mixed
     */
    public function getById($id, $relations = [], $withTrashed = false, $selects = [])
    {
        try {
            $query = $this->initiateQuery($relations, $withTrashed, $selects);
            return $query->find($id);
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        }
    }

    /**
     * @param $id
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @throws Illuminate\Database\Eloquent\ModelNotFoundException
     * @return mixed
     */
    public function getByIdOrFail($id, $relations = [], $withTrashed = false, $selects = [])
    {
        try {
            $query = $this->initiateQuery($relations, $withTrashed, $selects);
            return $query->findOrFail($id);
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        } catch (\Illuminate\Database\ModelNotFoundException $exc) {
            throw $exc;
        }
    }

    /**
     * @param $id
     * @param bool $withTrashed
     * @param array $selects
     * @return array
     */
    public function getByIdEmptyAttributes($id, $withTrashed = false, $selects = [])
    {
        try {
            $query = $this->initiateQuery([], $withTrashed, $selects);
            $model = $query->findOrFail($id);
            $blankValues = collect($model->getAttributes())->filter(function ($fieldValue, $fieldName) {
                return blank($fieldValue);
            })->toArray();

            return $blankValues;
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return [];
        }
    }

    /**
     * @param $key
     * @param $value
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return mixed
     */
    public function search($key, $value, array $relations = [], $withTrashed = false, $selects = [])
    {
        $query = $this->initiateQuery($relations, $withTrashed, $selects);
        return $query->where($key, 'like', '%' . $value . '%')
            ->get();
    }



    /**
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return mixed
     */
    public function getAll(array $relations = [], $withTrashed = false, $selects = [])
    {
        $query = $this->initiateQuery($relations, $withTrashed, $selects);
        return $query->get();
    }

    /**
     * Order by field, placing empty cells at end
     * be careful when using a function in the ORDER BY clause; MySQL cannot use an index in these cases. These queries will work fine on "small" tables but will slow down drastically as your project scales in size.
     *
     * @param string $column
     * @param string $direction
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return $this
     */
    public function getAllOrderByWithEmptyAtEnd($column, $direction = 'asc', $relations = [], $withTrashed = false, $selects = [])
    {
        try {
            $query = $this->initiateQuery($relations, $withTrashed, $selects);
            return $query->orderByRaw(" if($column = '' or $column is null,1,0),$column $direction")->get();
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        }
    }

    /**
     * @param bool $withTrashed
     * @return mixed
     */
    public function countAll($withTrashed = false)
    {
        $query = $this->model;
        if ($withTrashed)
            $query = $query->withTrashed();

        return $query->count();
    }

    /**
     * @param $key
     * @param string $attr
     * @return mixed
     */
    public function getAllSelectable($key, $attr = 'id')
    {
        return $this->model->pluck($key, $attr);
    }

    /**
     * @param $id
     * @param array $inputs
     * @return mixed
     */
    public function update($id, array $inputs)
    {
        try {
            $model = $this->getById($id);
            if ($model) {
                $model->update($inputs);
                return $model->fresh();
            } else
                return null;
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return null;
        }
    }

    /**
     * @param $id
     * @return bool
     */
    public function destroy($id)
    {
        try {
            $data = $this->getById($id);
            return $data ? $data->delete() : false;
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return false;
        }
    }

    /**
     * @return bool
     */
    public function destroyAll()
    {
        try {
            return $this->model->delete();
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return false;
        }
    }

    /**
     * @param $id
     * @return bool
     */
    public function forceDelete($id)
    {
        try {
            $data = $this->getById($id, [], true);
            return $data ? $data->forceDelete() : false;
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return false;
        }
    }

    /**
     * remove the entry in database if the model is already soft-deleted
     * soft delete the model the first time and the next time force delete it
     * @param $id
     * @return bool
     */
    public function destroyThenForceDelete( $id)
    {
        try {

            $elm = $this->model->withTrashed()->find($id);

            if (!$elm) {
                return false;
            }

            if($elm->trashed()){
                return $elm->forceDelete();
            }else{
                return $elm->delete();
            }

        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return false;
        }
    }



    /**
     * @param $id
     * @return bool
     */
    public function restore($id)
    {
        try {
            $data = $this->getById($id, [], true);
            return $data ? $data->restore() : false;
        } catch (\Illuminate\Database\QueryException $exc) {
            Log::error($exc->getMessage(), $exc->getTrace());
            return false;
        }
    }

    /**
     * @param array $relations
     * @param bool $withTrashed
     * @param array $selects
     * @return mixed
     */
    private function initiateQuery($relations = [], $withTrashed = false, $selects = [])
    {
        $query = $this->model;
        if (count($relations) > 0)
            $query = $query->with($relations);

        if (count($selects) > 0)
            $query->select($selects);

        if ($withTrashed)
            $query = $query->withTrashed();

        return $query;
    }


}
