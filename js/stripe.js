// Stripe Integration
const stripe = Stripe('YOUR_STRIPE_PUBLIC_KEY');

async function initializeStripeCheckout(cartTotal) {
    const response = await fetch('/api/create-checkout-session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            amount: cartTotal * 100, // Convert to cents
            currency: 'usd',
            customerEmail: localStorage.getItem('userEmail')
        })
    });

    const session = await response.json();

    const result = await stripe.redirectToCheckout({
        sessionId: session.id
    });

    if (result.error) {
        alert(result.error.message);
    }
}

// Handle Stripe redirect
if (window.location.search.includes('session_id')) {
    const sessionId = new URLSearchParams(window.location.search).get('session_id');

    fetch(`/api/verify-payment.php?session_id=${sessionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment successful!');
                localStorage.removeItem('freightCart');
                window.location.href = 'dashboard.html';
            }
        });
}