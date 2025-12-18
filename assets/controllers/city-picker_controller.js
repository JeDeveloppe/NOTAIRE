import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["container", "searchInput"];
    static values = {
        prototype: String,
        index: Number
    }

    // Appelé quand une ville est sélectionnée dans l'autocomplete
    addCity(event) {
        const cityId = event.detail.value; // L'ID de la ville
        const cityName = event.detail.text; // Le nom affiché (ex: Caen 14000)

        if (!cityId) return;

        // Vérifier si déjà ajouté
        if (this.containerTarget.querySelector(`[data-city-id="${cityId}"]`)) {
            alert('Cette ville est déjà dans votre liste.');
            return;
        }

        // 1. Créer le champ caché pour Symfony via le prototype
        const html = this.prototypeValue
            .replace(/__name__/g, this.indexValue);
        
        const wrapper = document.createElement('div');
        wrapper.classList.add('list-group-item', 'd-flex', 'justify-content-between', 'align-items-center', 'mb-2', 'shadow-sm');
        wrapper.setAttribute('data-city-id', cityId);
        
        wrapper.innerHTML = `
            <div>
                <i class="fas fa-city me-2 text-primary"></i>
                <strong>${cityName}</strong>
                <div class="d-none">${html}</div> 
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger" data-action="city-picker#remove">
                <i class="fas fa-times"></i>
            </button>
        `;

        // 2. Injecter la valeur dans le select masqué
        this.containerTarget.appendChild(wrapper);
        const hiddenSelect = wrapper.querySelector('select');
        if (hiddenSelect) hiddenSelect.value = cityId;

        this.indexValue++;
        
        // 3. Optionnel : vider le champ de recherche
        if (this.searchInputTarget.tomselect) {
            this.searchInputTarget.tomselect.clear();
        }
    }

    remove(event) {
        event.target.closest('.list-group-item').remove();
    }
}