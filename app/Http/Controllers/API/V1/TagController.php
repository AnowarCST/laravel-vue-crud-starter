<?php

namespace App\Http\Controllers\API\V1;

use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends BaseController
{
    public function index()
    {
        return $this->sendResponse(Tag::latest()->paginate(10), 'Tag list');
    }

    public function list()
    {
        return $this->sendResponse(Tag::get(['name', 'id']), 'Tag list');
    }

    public function show($id)
    {
        return $this->sendResponse(Tag::findOrFail($id), 'Tag Details');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Tag::class);

        return $this->sendResponse(Tag::create($this->validated($request)), 'Tag Created Successfully');
    }

    public function update(Request $request, $id)
    {
        $record = Tag::findOrFail($id);
        $this->authorize('update', $record);
        $record->update($this->validated($request));

        return $this->sendResponse($record, 'Tag Information has been updated');
    }

    public function destroy($id)
    {
        $record = Tag::findOrFail($id);
        $this->authorize('delete', $record);
        $record->delete();

        return $this->sendResponse($record, 'Tag has been Deleted');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:191']]);
    }
}
