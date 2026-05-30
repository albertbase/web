/**
 * Admin products grid — live stock/price updates without page refresh.
 */
(function () {
  const config = window.SWEETORIA_PRODUCTS_REALTIME || {};
  const feedBase = config.feedUrl || '/admin/products/live';
  const pollMs = Number(config.pollIntervalMs) || 2500;
  const noImageUrl = config.noImageUrl || '/images/no-image.png';
  const uploadsBase = config.uploadsBase || '/uploads/products/';
  const grid = document.querySelector('[data-products-grid]');
  const liveIndicator = document.getElementById('products-live-indicator');

  if (!grid) {
    return;
  }

  let pollFailures = 0;

  const setLiveStatus = (message, variant) => {
    if (!liveIndicator) {
      return;
    }
    liveIndicator.classList.remove('d-none', 'alert-info', 'alert-warning', 'alert-danger');
    liveIndicator.classList.add(
      variant === 'error' ? 'alert-danger' : variant === 'warn' ? 'alert-warning' : 'alert-info',
    );
    liveIndicator.innerHTML = message;
  };

  const buildPollUrl = () => {
    const url = new URL(feedBase, window.location.origin);
    const pageParams = new URLSearchParams(window.location.search);
    const search = pageParams.get('search');
    const category = pageParams.get('category');
    if (search) {
      url.searchParams.set('search', search);
    }
    if (category) {
      url.searchParams.set('category', category);
    }
    return url.toString();
  };

  const formatMoney = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) {
      return '₱0.00';
    }
    return `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const stockBadgeHtml = (stock) => {
    const n = Number(stock);
    if (n === 0) {
      return '<span class="badge bg-danger">Out of Stock</span>';
    }
    if (n <= 5) {
      return `<span class="badge bg-warning text-dark">Low Stock (${n})</span>`;
    }
    return `<span class="badge bg-success">In Stock (${n})</span>`;
  };

  const borderClassForStock = (stock) => {
    const n = Number(stock);
    if (n === 0) {
      return 'border-danger';
    }
    if (n <= 5) {
      return 'border-warning';
    }
    return '';
  };

  const imageSrc = (image) => {
    if (image) {
      return `${uploadsBase}${image}`;
    }
    return noImageUrl;
  };

  const updateProductCard = (product) => {
    if (!product?.id) {
      return;
    }

    const id = String(product.id);
    const col = grid.querySelector(`[data-product-id="${id}"]`);
    if (!col) {
      return;
    }

    const card = col.querySelector('.product-card');
    const stock = Number(product.stock);

    if (card) {
      card.classList.remove('border-danger', 'border-warning');
      const border = borderClassForStock(stock);
      if (border) {
        card.classList.add(border);
      }
      card.classList.add('product-card--updated');
      window.setTimeout(() => card.classList.remove('product-card--updated'), 1600);
    }

    const priceEl = col.querySelector('[data-product-price]');
    if (priceEl) {
      priceEl.textContent = formatMoney(product.price);
    }

    const categoryEl = col.querySelector('[data-product-category]');
    if (categoryEl) {
      categoryEl.textContent = product.categoryName || 'Uncategorized';
    }

    const stockWrap = col.querySelector('[data-stock-wrap]');
    if (stockWrap) {
      stockWrap.innerHTML = stockBadgeHtml(stock);
    }
  };

  const fetchProducts = async () => {
    try {
      const response = await fetch(buildPollUrl(), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
        cache: 'no-store',
      });

      if (!response.ok) {
        pollFailures += 1;
        if (pollFailures >= 2) {
          setLiveStatus(
            '<i class="bi bi-exclamation-triangle"></i> Live updates paused — refresh the page.',
            'error',
          );
        }
        return;
      }

      pollFailures = 0;
      setLiveStatus('<i class="bi bi-broadcast"></i> Live — stock and prices update automatically', 'ok');

      const payload = await response.json();
      const products = payload?.products ?? [];

      if (!Array.isArray(products)) {
        return;
      }

      products.forEach(updateProductCard);
    } catch {
      pollFailures += 1;
    }
  };

  const connectMercure = () => {
    if (typeof EventSource === 'undefined' || !config.mercureUrl) {
      return;
    }

    try {
      const url = new URL(config.mercureUrl, window.location.origin);
      ['inventory', 'notification', 'orders', 'new_order'].forEach((topic) => {
        url.searchParams.append('topic', topic);
      });

      const source = new EventSource(url.toString());
      const onEvent = () => {
        void fetchProducts();
      };

      source.addEventListener('message', onEvent);
      ['inventory', 'order_created', 'new_order', 'notification'].forEach((type) => {
        source.addEventListener(type, onEvent);
      });

      source.addEventListener('error', () => source.close());
    } catch {
      // Mercure optional.
    }
  };

  void fetchProducts();
  setInterval(fetchProducts, pollMs);
  connectMercure();
})();
