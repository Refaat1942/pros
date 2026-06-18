    document.querySelectorAll('.role-card').forEach(function(card, index) {
      card.style.opacity = '0';
      card.style.transform = 'translateY(24px)';
      setTimeout(function() {
        card.style.transition = 'opacity 0.5s ease, transform 0.5s ease, box-shadow 0.35s ease, border-color 0.35s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, 100 + index * 100);
    });
