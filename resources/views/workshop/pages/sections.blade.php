@push('styles')
@include('partials.dashboard-tailwind')
@endpush

@push('tailwind-theme')
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: { sans: ['Tajawal', 'sans-serif'] },
        colors: {
          workshop: { DEFAULT: '#7c3aed', dark: '#6d28d9', light: '#f5f3ff' }
        }
      }
    }
  }
</script>
@endpush

@include('partials.workshop-sections-panel', [
    'show_admin_employee_link' => false,
    'workshop_sections_api' => '/workshop/sections',
])
