<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

class CategoryRepository
{
    protected $model;

    public function __construct(Category $model)
    {
        $this->model = $model;
    }

    public function all(): Collection
    {
        return $this->model->all();
    }

    public function find($id): ?Category
    {
        return $this->model->find($id);
    }

    public function findBySlug($slug): ?Category
    {
        return $this->model->where('slug', $slug)->first();
    }

    public function create(array $data): Category
    {
        return $this->model->create($data);
    }

    public function update($id, array $data): bool
    {
        $category = $this->find($id);
        return $category ? $category->update($data) : false;
    }

    public function delete($id): bool
    {
        $category = $this->find($id);
        return $category ? $category->delete() : false;
    }

    public function getPopular($limit = 10): Collection
    {
        return $this->model->popular()->limit($limit)->get();
    }

    public function getAiGenerated(): Collection
    {
        return $this->model->aiGenerated()->get();
    }

    public function getManual(): Collection
    {
        return $this->model->manual()->get();
    }

    public function incrementUsage($id): bool
    {
        $category = $this->find($id);
        if ($category) {
            $category->incrementUsage();
            return true;
        }
        return false;
    }

    public function findOrCreateByName($name, $isAiGenerated = false): Category
    {
        $slug = \Str::slug($name);
        $category = $this->findBySlug($slug);

        if (!$category) {
            $category = $this->create([
                'name' => $name,
                'slug' => $slug,
                'is_ai_generated' => $isAiGenerated,
            ]);
        }

        return $category;
    }

    public function search($query): Collection
    {
        return $this->model->where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->get();
    }
}
