class FreightCalculator {
    constructor() {
        this.currentStep = 1;
        this.quoteData = {
            pickup: '',
            delivery: '',
            distance: 0,
            size: '',
            dimensions: { length: 0, width: 0, height: 0 },
            weight: 0,
            deliveryType: 'standard',
            totalPrice: 0
        };
    }

    nextStep(step) {
        document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
        this.currentStep = step;
    }

    prevStep(step) {
        this.nextStep(step);
    }

    calculateQuote() {
        // Mock calculation - replace with API call
        const distance = this.quoteData.distance || 100;
        const baseRate = 10;
        const distanceRate = distance * 1.5;
        const weightSurcharge = this.quoteData.weight * 0.5;

        let sizeMultiplier = 1;
        switch (this.quoteData.size) {
            case 'small': sizeMultiplier = 1; break;
            case 'medium': sizeMultiplier = 1.5; break;
            case 'large': sizeMultiplier = 2.5; break;
            case 'custom': sizeMultiplier = this.calculateVolumeMultiplier(); break;
        }

        let deliveryMultiplier = 1;
        switch (this.quoteData.deliveryType) {
            case 'express': deliveryMultiplier = 1.5; break;
            case 'dangerous': deliveryMultiplier = 1.75; break;
            case 'international': deliveryMultiplier = 2.0; break;
        }

        this.quoteData.totalPrice = (baseRate + distanceRate + weightSurcharge) *
            sizeMultiplier * deliveryMultiplier;

        this.displayQuote();
        this.nextStep(4);
    }

    addToCart() {
        const cart = JSON.parse(localStorage.getItem('freightCart') || '[]');
        cart.push({
            ...this.quoteData,
            id: Date.now(),
            timestamp: new Date().toISOString()
        });
        localStorage.setItem('freightCart', JSON.stringify(cart));
        alert('Added to cart!');
    }

    bookNow() {
        if (!localStorage.getItem('userLoggedIn')) {
            alert('Please login or register to book a shipment');
            window.location.href = '../pages/login.html';
            return;
        }
        this.addToCart();
        window.location.href = '../pages/cart.html';
    }
}

// Initialize calculator
const calculator = new FreightCalculator();