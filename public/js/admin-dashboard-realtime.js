/**
 * Admin dashboard — live metrics, recent orders, and product stock without refresh.
 */
(function () {
  const config = window.SWEETORIA_DASHBOARD_REALTIME || {};
  const feedUrl = config.feedUrl || '/admin/dashboard/live';
  const pollMs = Number(config.pollIntervalMs) || 2500;
  const liveIndicator = document.getElementById('dashboard-live-indicator');
  const ordersTbody = document.querySelector('[data-dashboard-orders-table] tbody');
  const productsTbody = document.querySelector('[data-dashboard-products-table] tbody');
  const logsTbody = document.querySelector('[data-dashboard-logs-table] tbody');

  if (!ordersTbody || !productsTbody || !logsTbody) {
    return;
  }

  const statusBadgeClass = {
    Pending: 'bg-warning text-dark',
    Paid: 'bg-info text-dark',
    Shipped: 'bg-primary',
    Delivered: 'bg-success',
  };

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

  const formatMoney = (value) => {
    const n = Number(value);
    if (!Number.isFinite(n)) {
      return '₱0.00';
    }
    return `₱${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
  };

  const formatDate = (iso) => {
    if (!iso) {
      return '—';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
      return iso;
    }
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const pad = (x) => String(x).padStart(2, '0');
    return `${months[d.getMonth()]} ${pad(d.getDate())}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  const stockBadgeHtml = (stock) => {
    const n = Number(stock);
    if (n === 0) {
      return '<span class="badge bg-danger" data-stock-badge>Out (0)</span>';
    }
    if (n <= 5) {
      return `<span class="badge bg-warning text-dark" data-stock-badge>Low (${n})</span>`;
    }
    return `<span class="badge bg-success" data-stock-badge>In (${n})</span>`;
  };

  const updateMetrics = (metrics) => {
    if (!metrics || typeof metrics !== 'object') {
      return;
    }
    Object.entries(metrics).forEach(([key, value]) => {
      const el = document.querySelector(`[data-metric="${key}"]`);
      if (el) {
        el.textContent = String(value);
      }
    });
  };

  const upsertOrder = (order) => {
    if (!order?.id) {
      return;
    }

    const id = String(order.id);
    const status = order.status || 'Pending';
    const badge = statusBadgeClass[status] || 'bg-secondary';
    let row = ordersTbody.querySelector(`tr[data-order-id="${id}"]`);

    if (row) {
      row.children[2].innerHTML = `<span class="badge ${badge} px-2 py-1">${status}</span>`;
      row.children[3].textContent = formatMoney(order.totalAmount);
      return;
    }

    const emptyRow = ordersTbody.querySelector('tr:not([data-order-id])');
    if (emptyRow) {
      emptyRow.remove();
    }

    ordersTbody.insertAdjacentHTML(
      'afterbegin',
      `<tr data-order-id="${id}" class="row--new">
        <td class="fw-bold">#${id}</td>
        <td>${order.customerName || ''}</td>
        <td><span class="badge ${badge} px-2 py-1">${status}</span></td>
        <td class="fw-semibold">${formatMoney(order.totalAmount)}</td>
        <td class="text-muted small">${formatDate(order.createdAt)}</td>
      </tr>`,
    );
  };

  const upsertProduct = (product) => {
    if (!product?.id) {
      return;
    }

    const id = String(product.id);
    let row = productsTbody.querySelector(`tr[data-product-id="${id}"]`);

    if (row) {
      const stockCell = row.children[1];
      if (stockCell) {
        stockCell.innerHTML = stockBadgeHtml(product.stock);
      }
      const priceEl = row.querySelector('[data-product-price]');
      if (priceEl) {
        priceEl.textContent = formatMoney(product.price);
      }
      return;
    }

    const emptyRow = productsTbody.querySelector('tr:not([data-product-id])');
    if (emptyRow) {
      emptyRow.remove();
    }

    const categoryLine = product.categoryName
      ? `<br><small class="text-muted">${product.categoryName}</small>`
      : '';

    productsTbody.insertAdjacentHTML(
      'afterbegin',
      `<tr data-product-id="${id}" class="row--new">
        <td><span class="fw-semibold">${product.name || ''}</span>${categoryLine}</td>
        <td>${stockBadgeHtml(product.stock)}</td>
        <td class="fw-semibold" data-product-price>${formatMoney(product.price)}</td>
      </tr>`,
    );
  };

  const upsertLog = (log) => {
    if (!log?.id) {
      return;
    }

    const id = String(log.id);
    let row = logsTbody.querySelector(`tr[data-log-id="${id}"]`);
    if (row) {
      return; // Logs are immutable, if it's there we don't update it
    }

    const emptyRow = logsTbody.querySelector('tr:not([data-log-id])');
    if (emptyRow) {
      emptyRow.remove();
    }

    const entityHtml = log.entityType
      ? `<span class="text-muted">${log.entityType}</span> <span class="fw-bold text-dark">#${log.entityId}</span>`
      : '<span class="text-muted">—</span>';
      
    const detailsHtml = log.details ? log.details : 'No additional data';

    logsTbody.insertAdjacentHTML(
      'afterbegin',
      `<tr data-log-id="${id}" class="row--new">
        <td><span class="log-user">${log.username || ''}</span></td>
        <td><small class="badge bg-light text-dark border">${log.userRole || ''}</small></td>
        <td><span class="action-badge">${log.action || ''}</span></td>
        <td>${entityHtml}</td>
        <td><div class="log-details text-truncate" style="max-width: 200px;">${detailsHtml}</div></td>
        <td class="text-muted small">${formatDate(log.timestamp)}</td>
      </tr>`,
    );

    // Keep only the most recent 10 rows to match the backend limit
    while (logsTbody.children.length > 10) {
        logsTbody.lastElementChild.remove();
    }
  };

  const handlePayload = (payload) => {
    updateMetrics(payload?.metrics);
    (payload?.recentOrders ?? []).reverse().forEach(upsertOrder);
    (payload?.recentProducts ?? []).reverse().forEach(upsertProduct);
    (payload?.recentLogs ?? []).reverse().forEach(upsertLog);
  };

  const fetchDashboard = async () => {
    try {
      const response = await fetch(feedUrl, {
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
      setLiveStatus('<i class="bi bi-broadcast"></i> Live — orders and inventory update automatically', 'ok');

      const payload = await response.json();
      handlePayload(payload);
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
      ['orders', 'admin_orders', 'new_order', 'order_created', 'inventory', 'notification', 'activity_log'].forEach(
        (topic) => url.searchParams.append('topic', topic),
      );

      const source = new EventSource(url.toString());
      const onEvent = () => {
        void fetchDashboard();
      };

      source.addEventListener('message', onEvent);
      ['order_created', 'new_order', 'inventory', 'notification', 'activity_log'].forEach((type) => {
        source.addEventListener(type, onEvent);
      });

      source.addEventListener('error', () => source.close());
    } catch {
      // Mercure optional.
    }
  };

  void fetchDashboard();
  setInterval(fetchDashboard, pollMs);
  connectMercure();
})();
