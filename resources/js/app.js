import './bootstrap';
import Alpine from 'alpinejs';
import Livewire from '@livewire/alpine-plugin';

Alpine.plugin(Livewire);
Alpine.start();

document.addEventListener('livewire:load', () => {
    window.Alpine = Alpine;
    Alpine.start();
});