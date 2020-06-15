<?php

namespace App\Repositories;

interface ProductRepositoryInterface
{
    /**
     * Get all
     * @return mixed
     */
    public function get();


    /**
     * Get one
     * @param $id
     * @return mixed
     */
    public function find($id);

    /**
     * Create
     * @param array $attributes
     * @return mixed
     */
    public function create(array $attributes);

    /**
     * Update
     * @param $id
     * @param array $attributes
     * @return mixed
     */
    public function update($id, array $attributes);

    /**
     * Delete
     * @param $id
     * @return mixed
     */
    public function delete($id);

    /**
     * Pagination
     * @param $perPage
     * @return mixed
     */
    public function paginate($perPage);

    /**
     * Pagination
     * @return mixed
     */
    public function latest();
}
