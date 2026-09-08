<div class="max-w-4xl mx-auto space-y-6">
    <flux:heading size="xl" class="mb-4">Pengaturan & Jalan Pintas</flux:heading>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Settings -->
        <flux:card>
            <flux:heading size="lg">Workflow & Mode Operasional</flux:heading>
            <flux:subheading>Atur approval dan mode solo/team.</flux:subheading>
            <div class="mt-4">
                <flux:button href="{{ route('settings.workflow') }}" variant="primary" icon="adjustments-horizontal">
                    Buka Pengaturan Workflow
                </flux:button>
            </div>
        </flux:card>

        <!-- Docs -->
        <flux:card>
            <flux:heading size="lg">Panduan Penggunaan</flux:heading>
            <flux:subheading>Baca dokumentasi dan tutorial.</flux:subheading>
            <div class="mt-4">
                <flux:button href="{{ route('docs.index') }}" variant="primary" icon="book-open">
                    Buka Panduan
                </flux:button>
            </div>
        </flux:card>

        <!-- Reports -->
        <flux:card>
            <flux:heading size="lg">Laporan Utama</flux:heading>
            <flux:subheading>Lihat analitik dan grafik performa bisnis.</flux:subheading>
            <div class="mt-4">
                <flux:button href="{{ route('dashboard') }}" variant="primary" icon="chart-pie">
                    Buka Laporan
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>
