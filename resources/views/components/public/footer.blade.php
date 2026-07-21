<footer class="public-footer py-5" aria-labelledby="footer-brand">
    <div class="container">
        <div class="row g-4">
            <section class="col-lg-5">
                <h2 class="h4" id="footer-brand">Almacenes al Costo</h2>
                <p class="mb-0 footer-copy">La información comercial y los canales oficiales se publicarán cuando hayan sido confirmados.</p>
            </section>
            <nav class="col-sm-6 col-lg-3" aria-label="Navegación del pie de página">
                <h2 class="h5">Navegación</h2>
                <ul class="list-unstyled footer-links mb-0">
                    <li><a href="{{ route('home') }}">Inicio</a></li>
                    <li><a href="{{ route('catalog.index') }}">Catálogo</a></li>
                    <li><a href="{{ route('categories.index') }}">Categorías</a></li>
                    <li><a href="{{ route('promotions.index') }}">Promociones</a></li>
                    <li><a href="{{ route('information') }}">Información</a></li>
                </ul>
            </nav>
            <section class="col-sm-6 col-lg-4">
                <h2 class="h5">Información institucional</h2>
                <p class="mb-0 footer-copy">Dirección, horario y medios de contacto: pendientes de publicación.</p>
            </section>
        </div>
        <p class="footer-legal mt-4 pt-3 mb-0 small">&copy; {{ now()->year }} Almacenes al Costo. Todos los derechos reservados.</p>
    </div>
</footer>
