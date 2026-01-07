<?php

namespace Whitecube\NovaFlexibleContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Whitecube\NovaFlexibleContent\Layouts\LayoutInterface;

class LayoutFieldsController extends Controller
{
    /**
     * Get the fields for a specific layout class
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $layoutClass = $request->input('layout');

        // Validate the class exists and implements LayoutInterface
        if (!$layoutClass || !class_exists($layoutClass)) {
            abort(404, 'Layout class not found');
        }

        if (!is_a($layoutClass, LayoutInterface::class, true)) {
            abort(400, 'Class does not implement LayoutInterface');
        }

        // Instantiate the layout
        $layout = new $layoutClass();

        // Resolve fields with empty values (for form rendering)
        $layout->resolve(true);

        // Get the fields collection
        $fields = $layout->fields();

        return response()->json([
            'name' => $layout->name(),
            'title' => $layout->title(),
            'fields' => $fields instanceof \JsonSerializable
                ? $fields->jsonSerialize()
                : $fields,
            'limit' => $layout->limit ?? null,
        ]);
    }
}
