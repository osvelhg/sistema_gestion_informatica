<?php

namespace Database\Seeders;

use App\Models\WorksheetAspect;
use Illuminate\Database\Seeder;

class WorksheetAspectSeeder extends Seeder
{
    public function run(): void
    {
        $aspects = [
            // Revisión de Equipamiento
            ['section' => 'equipamiento', 'order' => 1,  'label' => 'Sello y estado técnico de PC'],
            ['section' => 'equipamiento', 'order' => 2,  'label' => 'Sello y estado técnico de Backup'],
            ['section' => 'equipamiento', 'order' => 3,  'label' => 'Estado técnico de monitor'],
            ['section' => 'equipamiento', 'order' => 4,  'label' => 'Estado técnico de Impresora'],
            ['section' => 'equipamiento', 'order' => 5,  'label' => 'Orden de booteo'],
            ['section' => 'equipamiento', 'order' => 6,  'label' => 'Contraseña del Setup'],
            ['section' => 'equipamiento', 'order' => 7,  'label' => 'Limpieza y organización de las Estaciones de Trabajo'],
            ['section' => 'equipamiento', 'order' => 8,  'label' => 'Puertos USB Bloqueados'],
            ['section' => 'equipamiento', 'order' => 9,  'label' => 'Cables de red protegidos por canaletas'],
            ['section' => 'equipamiento', 'order' => 10, 'label' => 'Tomacorriente 110v y 220v señalizados'],

            // Revisión de Software y Sistemas
            ['section' => 'software', 'order' => 1, 'label' => 'Antivirus actualizado y configurado correctamente'],
            ['section' => 'software', 'order' => 2, 'label' => 'Softwares establecidos'],
            ['section' => 'software', 'order' => 3, 'label' => 'Softwares actualizados'],
            ['section' => 'software', 'order' => 4, 'label' => 'Contenidos no permitidos (Fotos, Video, Música)'],
            ['section' => 'software', 'order' => 5, 'label' => 'Carpetas personales y documentos en el Disco D:'],
            ['section' => 'software', 'order' => 6, 'label' => 'Configuración de las secciones del dominio como está establecido'],

            // Salvas
            ['section' => 'salvas', 'order' => 1, 'label' => 'Se están realizando las salvas de los documentos y carpetas personales. Salvas cruzadas.'],
        ];

        foreach ($aspects as $aspect) {
            WorksheetAspect::firstOrCreate(
                ['section' => $aspect['section'], 'label' => $aspect['label']],
                ['order' => $aspect['order'], 'active' => true],
            );
        }
    }
}
