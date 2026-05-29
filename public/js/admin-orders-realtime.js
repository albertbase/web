/**
 * Admin orders list — live updates without full page refresh.
 * Uses API polling (always) + Mercure SSE when the hub is available.
 */
(function () {
  const config = window.SWEETORIA_ADMIN_REALTIME || {};
  const ordersApiUrl = config.ordersApiUrl || '/api/admin/orders';
  const mercureUrl = config.mercureUrl || '/.well-known/mercure';
  const pollMs = Number(config.pollIntervalMs) || 4000;

  const table = document.querySelector('[data-orders-table]');
  const tbody = table?.querySelector('tbody');
  if (!tbody) {
    return;
  }

  const statusBadgeClass = {
    Pending: 'bg-warning text-dark',
    Paid: 'bg-info text-dark',
    Shipped: 'bg-primary',
    Delivered: 'bg-success',
  };

  const knownOrderIds = new Set(
    Array.from(tbody.querySelectorAll('tr[data-order-id]')).map((row) =>
      String(row.getAttribute('data-order-id')),
    ),
  );

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
    const pad = (x) => String(x).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  };

  const buildRowHtml = (order) => {
    const id = order.id;
    const status = order.status || 'Pending';
    const badge = statusBadgeClass[status] || 'bg-secondary';
    const showUrl = config.orderShowUrlTemplate
      ? config.orderShowUrlTemplate.replace('__ID__', String(id))
      : `#`;

    return `
      <tr class="order-row" data-order-id="${id}">
        <td class="fw-bold">#${id}</td>
        <td>${order.customerName || ''}</td>
        <td><span class="badge ${badge} px-3 py-2">${status}</span></td>
        <td class="fw-semibold">${formatMoney(order.totalAmount)}</td>
        <td>${formatDate(order.createdAt)}</td>
        <td class="text-center">
          <a href="${showUrl}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
        </td>
      </tr>`;
  };

  const upsertOrder = (order) => {
    if (!order?.id) {
      return;
    }

    const id = String(order.id);
    const existing = tbody.querySelector(`tr[data-order-id="${id}"]`);

    if (existing) {
      const status = order.status || 'Pending';
      const badge = statusBadgeClass[status] || 'bg-secondary';
      existing.children[2].innerHTML = `<span class="badge ${badge} px-3 py-2">${status}</span>`;
      existing.children[3].textContent = formatMoney(order.totalAmount);
      return;
    }

    const emptyRow = tbody.querySelector('tr:not([data-order-id])');
    if (emptyRow) {
      emptyRow.remove();
    }

    tbody.insertAdjacentHTML('afterbegin', buildRowHtml(order));
    const newRow = tbody.querySelector(`tr[data-order-id="${id}"]`);
    newRow?.classList.add('order-row--new');
    knownOrderIds.add(id);

    if (typeof window.showSweetoriaToast === 'function') {
      window.showSweetoriaToast(`New order #${id} received`);
    }
  };

  const fetchOrders = async () => {
    try {
      const response = await fetch(ordersApiUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        return;
      }

      const payload = await response.json();
      const orders = payload?.data?.orders ?? payload?.orders ?? [];

      if (!Array.isArray(orders)) {
        return;
      }

      orders.forEach(upsertOrder);
    } catch {
      // Ignore transient network errors.
    }
  };

  const handleMercurePayload = (data) => {
    if (!data) {
      return;
    }

    const embedded = data.order;
    if (embedded?.id) {
      upsertOrder(embedded);
      return;
    }

    if (data.orderId) {
      upsertOrder({
        id: data.orderId,
        customerName: data.customerName,
        status: data.status,
        totalAmount: data.totalAmount,
        createdAt: data.createdAt,
      });
    }
  };

  const connectMercure = () => {
    if (typeof EventSource === 'undefined') {
      return;
    }

    try {
      const url = new URL(mercureUrl, window.location.origin);
      [
        'orders',
        'admin_orders',
        'new_order',
        'order_created',
        'order_status',
        'notification',
      ].forEach((topic) => url.searchParams.append('topic', topic));

      const source = new EventSource(url.toString());

      const onEvent = (event) => {
        try {
          handleMercurePayload(JSON.parse(event.data));
        } catch {
          // ignore
        }
      };

      source.addEventListener('message', onEvent);
      ['order_created', 'new_order', 'order_status', 'notification'].forEach((type) => {
        source.addEventListener(type, onEvent);
      });

      source.addEventListener('error', () => {
        source.close();
      });
    } catch {
      // Mercure not available on this host.
    }
  };

  void fetchOrders();
  setInterval(fetchOrders, pollMs);
  connectMercure();
})();
