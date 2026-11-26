</main>

<footer class="kz-footer mt-5">
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-md-5">
                <h5 class="fw-bold mb-2"><i class="fa-solid fa-futbol me-1 text-kz-verde"></i> KickZone</h5>
                <p class="small mb-0 text-white-50">
                    Centro Deportivo Integral. Reserva canchas de Fútbol 5, Fútbol 7, Pádel y Baloncesto
                    en línea, con tarifa nocturna automática y alquiler de implementos.
                </p>
            </div>
            <div class="col-6 col-md-3">
                <h6 class="text-uppercase small fw-bold">Navegación</h6>
                <ul class="list-unstyled small mb-0">
                    <li><a href="<?= e(url('index.php#canchas')) ?>">Canchas</a></li>
                    <li><a href="<?= e(url('index.php#implementos')) ?>">Implementos</a></li>
                    <li><a href="<?= e(url('login.php')) ?>">Mi cuenta</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h6 class="text-uppercase small fw-bold">Horario</h6>
                <p class="small mb-1 text-white-50"><i class="fa-regular fa-clock me-1"></i>Lunes a domingo, 7:00 – 23:00</p>
                <p class="small mb-0 text-white-50"><i class="fa-solid fa-moon me-1 text-kz-ambar"></i>Tarifa nocturna desde las 18:00</p>
            </div>
        </div>
        <hr class="border-secondary">
        <p class="small text-center mb-0 text-white-50">
            &copy; <?= date('Y') ?> KickZone · Proyecto Final Desarrollo Web · Universidad Tecnológica de Panamá
        </p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<?php foreach (($scriptsExtra ?? []) as $script): ?>
    <script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
