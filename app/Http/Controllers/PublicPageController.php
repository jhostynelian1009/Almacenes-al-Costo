<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'featuredBanners' => collect(),
            'featuredProducts' => collect(),
        ]);
    }

    public function catalog(): View
    {
        return $this->section(
            title: 'Catálogo',
            description: 'Consulta el acceso inicial al catálogo de Almacenes al Costo.',
            message: 'El catálogo de productos se habilitará en una funcionalidad posterior.',
        );
    }

    public function categories(): View
    {
        return $this->section(
            title: 'Categorías',
            description: 'Consulta el acceso inicial a las categorías de Almacenes al Costo.',
            message: 'Las categorías se publicarán cuando el catálogo dinámico esté disponible.',
        );
    }

    public function promotions(): View
    {
        return $this->section(
            title: 'Promociones',
            description: 'Consulta el acceso inicial a promociones de Almacenes al Costo.',
            message: 'Las promociones vigentes se mostrarán en una funcionalidad posterior.',
        );
    }

    public function information(): View
    {
        return $this->section(
            title: 'Información',
            description: 'Información institucional de Almacenes al Costo.',
            message: 'La información comercial y los canales oficiales están pendientes de publicación.',
        );
    }

    private function section(string $title, string $description, string $message): View
    {
        return view('public.section', compact('title', 'description', 'message'));
    }
}
