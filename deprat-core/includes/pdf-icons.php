<?php
/** Pictogrammes techniques extraits du PDF fourni : raster 80x80 normalisé, sans téléchargement externe. */
defined('ABSPATH') || exit;

/**
 * Les données représentent les traits visibles des illustrations techniques
 * du PDF (zlib d'un bitmap 1bit, sans code exécutable ni SVG provenant d'utilisateurs).
 * Les 3 nouveaux identifiants sont stables pour les affectations enregistrées.
 */
function deprat_pdf_icon_sources() {
    return {
    'tubes' => 'eNrNkjFOAzEQRf/EaJditVASyRIcYctUKEexFAnaRaJIQeGIgo6ao1CaJjUHQMi3oOV7ZjZnyFZP9sz308wCZ/dtTnRcIITsNKI43SA5JXwbSAkfRj3GS6MBmwujCcmpSllZbu5h1CFCPHeC6HMzKnqlwvdjo8BCTFlFIoOy+s4MapSk8JwkObSgrCIManmRhRRUkWrnKLwYWKgivE8m0gSBW81tDU+5sGFoDTuKMIAv3JsgKVoQ6ZkNXRvE4WiCkNeVCpLeA/Pb9ORTTARS8aYioOuPTz6z3Ea7pYgNPoVtb7O7G8vg81yXyej6MVWj9e/ON3W1f/D1dHW/rDHPTvK1rBF/p3W/nN+/iH9FpT4G',
    'embouts' => 'eNrV0jFOwzAUBuDfNdgMATMWEbVHgAkYStorcINKXRiYYKAShfoAqHM3OEJvgFEl2OhaiQFvXSpkhAQZSoufnRyinj4lfvZ7fwKsxcpL8GWp+lgX6sMUWsIW23LuopRVw6JAZ1tlQS8JYAvmNoKkk0aUBYg61RnqQb++gZtw7sfYsq9QsJoZ/hzOXfWhGDXT/1wgI7HFu0NOUnnTMsv8y0OntDSkVoPO5V6m1kUDFS+96eCwQ6oYbv1TMLCW0piS/E7QRF6PXQg/L+PmzGGfLpPu2OCOWnk4Pyiy+rtPwds04neaYpsC4vNeimYYcfSW4IKUjWsJG5FuB5eJCOn9nMxFXYd0OkNxFHJaDbpiGjTZszJG+3JlZAz+dQIVk+08hRn8una6GtW07d3yC1TbUQJSr8WP9w/QLl9y',
    'flasque_guidage_bague' => 'eNq9kjFOw0AQRf96HdtEJjFSCiNF2OEELpBIQxK4AaJFwlXq9BiySpBoKCgpKHKEUNNsxCUonY7CRQqEglKE2V2bI2SllZ5Wnv9n/hjYzXHzikK/opSJkgRSAwwIDFnNvPzQStqOIXvAWUkC0hTvAfFAU51q44r8oHJxPFMBcFtTi2weNLXpTkxTdM//KdbSqi4oKUOgTTz2LjxDfDiwtZ23/xob8k9HgaWN/UJ6hmpTYTPdgtOAYyjsogHdQpKib2ghsYFS5i54IS6USBvus6YwQZTiWGWW407IDo0wFeyHiY7W5V+uIj9Ecx6JA6A7Qk/eKxpL3OJXpXop2TdfqpGGsfvZnCgqgijtO4pW3pnIQiIm7Q1bpepN8sJdSkUzdx5N9W5mh7JHiyG3PBbrRNNATqlA0RUeF3Md5TVuHMqXuu8iCx0d9AnWCReM1tBiuWS5RUJ16wPI2pRLzX+iQHKStN5eaBiVBhuTLtuqLWyrnwVH2N35A7H3WEs=',
    'supports_arbres' => 'eNqt0b9Lw0AUwPGLFw1KbEdtre2gq5MgCRUsbv4JLhJwFhoyuIhcqBhRQahQLf6I/4N/gFcKDiLt4OSW0sHFQTBuAX33LhcQHZslH8jjm+MeIaN59EzlTFWm9B6loIGVakJ7TmWS3UwFpmTyPypXlSpvSjM32VdXqdRRymtKUxo3mFIlkrK5h8cxi0u835Pa6T58Sm30tjFuFjdfu4d1lPtNG5eyklCWSMWULaZaYCs4p8Uem0v1cjop9XUeTjOp+/BAKh62HuEnuqhcrdfgXoROhg4hY0JnAzgrFWpEIO0Y5PM7CArdEqEnUIjaAl0woX1QE+eWRdkRsn3oRUKrg5gGqDUbyjhn92N6hBXLU2WrAGXsWbk92sI5iya0WUPBHbQjKT0tCwWZ5EmFGPmt2X+Uy0R9Pc9RpKNbdYL7cnWnJPc5P84NA/cKL3qN0mAZH3LZDhnd8wP/MIoG',
    'sangle' => 'eNqt0rFKxDAYB/B/0lybnkVqHbw66OGd59rDxUGwwgnVyUc49QU6Cg4GUdykgrt9hBsdHXyAGx07iaMPIGJIvnS68QqBH+mXf0LyAcv7WCueO0WZ00HsNKycvLbuYkHKQrk8eTynma/9H6sgqCl5NWtI6V1J2iquSbejyGkuKOWo9illT3H6e6U80i+YFXvNSf42YBUNHmm33kMmbd64m1Py5a7iVh9n4Ocm5O0eMPLSAsyIjyqSf9IniaJPdT5yknhRpGimqE6mcAqdwh3Q+UKOqT2BBEqrnkDdaipMXay3sEoKnWJuI9FLwpnTmlmR6LFeOQ1iJ2G0qcepUVeveDavJSXYuKET8BtlNEHnyVxLOMGKveVOgqGVYPi08vFXUq8ovNPbfwdtUx06bTRt8y2xpfEPX6Y+SA==',
    'liaison' => 'eNrF0TtOxDAQBuDfceSksKJINK4gR7C0DcVK5Ci+ASsOACOtRL3HoTQdBWdYUVBSUG4HM/bERwBLtj4lnodt4M/H0BTWTe9RYaxXdTiqJiSquqKQqpbsQtUajasij9eahGvEXJMYGkvuHkjdKOIlmGfVhHvRDFg8lcQ888+mXSksbd6UzFL9uuiF5105jjT8KHqwvHzJp85kmDdW7HGAPUmOHgHDXMs6TNIUjbDkpQ7xj1zOJoq3SZvykWsb4i3DQbrILPMtihJ2EXnZ8ikRTnSUzFwfHV20eZf3eqB+LVcQapSEcDv1Sg0hLHqlmFURUV/GI85VFosKlPQRkNLYHnV7Qddk8iZ8NIUmP22yTTg37fF/4xfNUy12',
    'coulisses' => 'eNqt0r1OAkEQB/BZj9xZmKuIgQiJBaUFCRfkIXwICkMsTKRDEz32Eex5FIu7gpbQaiVPoBQWV1zhzM5/9gnYZDe/3O3Mzn4QnbJdRy0N7t2Uxm/DT9Pk2bRrgeT2D8p/t1C/6kCLFnJfTccCDqnq4tufIe+aoJ8WcpsjlD7VUL/y0KokaNdAnBfK9zV0VRG0aCG3aaBsdZDsWgiPUt+kJEg3yEo+jpAUIrOlkLB2j2ha2vm9NUE8RQrhOO7rEJCLws8Z97ugOfeHsM/alMlwgxWgsQwjGULOQvJ6kx5joYVw68aL7CJvUKIne6mFqGZRc9WUnDdluLyCBlFjaEQ1dJ94U26v4rFneo2v58UCcAbSBlHnJ33Q9A+AYTz0',
    'tirage' => 'eNpjYKAWcICzHsAYjB8wWQw34KwzcFY6nFUM12xpAWPJz4Gx+J/AWHxlcLHfcNZtOOssjMXjD2edh7MOwdUdwGTxwT3CM8oaSqwDWJIixQAAMCsdlA==',
    'jalousie' => 'eNpjYKAOYISz2A/AWJwfYCyBepiggD1MUN7wB5Rls3EflKX2+XwQDhKW++TNUi7Lxzw1QHebvAiAs3ue/oSyG+ucvoCyZwm9QFkvBfSiLzcIMaiCTBcwNjB/+P4BKP/gLNZohYV4NlBXRJw9l+fXxQzX7n2OGanHLY4B6wMFGPgLK4n2w8dA==',
    'bras' => 'eNqt0LENAjEMhWGHQ9EJUVyZCjEARVoaREnBECkpKBngRqFkjIzACIxAcQPQ4D8n2eWl+uTI8XNEFjx7dENX9ECTonujqooXVUL5paq9NsioDUGVJP9VuOXdjvFblM7UUOR2MzodjrLVUKh9FKFFeVL7Oio28kpsvLWzUO/EG5ygB3RHLXK14o8liLOQWM3+Ee3QCR2d8NGRN6OlmmVe9PwAFC0WJQ==',
    'treuil' => 'eNqt0r9Lw0AUB/D3ktZUDIkgiEKxheIoBCuiFU0nRxdxEAULzoqig5PNIg4iDkXopG46Cg7ioF7pUKfSzU5ScbBCh4LiL6Lx3uXuPzCQ8Bnu3bvc+wL817OmYASelJ1oSLnptlr29B4Cg55AFdy3wpK+wA9yQrmbX/dcqFJ17brQ7sNkpSVKjVom6CVpRrF/sksoWoTaDiliFLE5TRtG9Dq+pbIkbMO3S1tHoAZfEydcMdiCoaBGQgem6tQkpjNIrzSlRk43pUaP36RSi9+iFiDVVEqf/SgdXvOviSXmFlY9OsFye3ivSsLBO3v/N5Qf3QmydJbnG4iecnXA9KulXXJpulG2tcKJkH2AsyQwxneFEPTHMi6EKpe8W36t6KFZglv6EQ8vGFyRGHwweCFlYYYl54Uc19sSSjoJ8IUc/m7PkbpJSzSmGFPqbABm1kkRrqMNktYGNDdosDrdpxDSRnkxbA/GIM9ANO6WonV5MWFqlw9Hzd9w/ia/OV9Igzh+huHwBlBmh1m6zJNjdcgQxXmRzA5vp/KkwgbWv4Ua/gAb1ooy'
};
}

/** Affichage des dessins du PDF sous forme de tracés SVG internes. */
function deprat_pdf_icon_markup($key, $class = 'deprat-section-icon', $size = 30) {
    static $cache = array();
    $sources = deprat_pdf_icon_sources();
    if (!isset($sources[$key])) { return ''; }
    if (!isset($cache[$key])) {
        $compressed = base64_decode($sources[$key], true);
        $bits = $compressed === false ? false : @gzuncompress($compressed);
        if (false === $bits || 800 !== strlen($bits)) { return ''; }
        $path = '';
        for ($y = 0; $y < 80; $y++) {
            $x = 0;
            while ($x < 80) {
                $position = $y * 80 + $x;
                $present = (ord($bits[intdiv($position, 8)]) >> (7 - ($position % 8))) & 1;
                if (!$present) { $x++; continue; }
                $start = $x;
                while ($x < 80) {
                    $position = $y * 80 + $x;
                    if (!((ord($bits[intdiv($position, 8)]) >> (7 - ($position % 8))) & 1)) { break; }
                    $x++;
                }
                $width = $x - $start;
                $path .= 'M' . $start . ' ' . $y . 'h' . $width . 'v1h-' . $width . 'z';
            }
        }
        $cache[$key] = $path;
    }
    return '<svg class="' . esc_attr($class) . '" role="img" aria-hidden="true" width="' . absint($size)
        . '" height="' . absint($size) . '" viewBox="0 0 80 80" preserveAspectRatio="xMidYMid meet"'
        . ' fill="#20518F"><path d="' . $cache[$key] . '"/></svg>';
}
