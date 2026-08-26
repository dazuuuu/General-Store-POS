(function (global) {
    function buckets() {
        return { retail: 0, retailPack: 0, wholesale: 0 };
    }
    function hasRetailPack(p) {
        return !!(p && p.packUnit && p.unitsPerPack > 1 && p.retailPackPrice > 0);
    }
    function hasWholesalePack(p) {
        return !!(p && p.packUnit && p.unitsPerPack > 1 && p.packPrice > 0);
    }
    function packLabel(p) {
        return (p && p.packUnit) ? p.packUnit : 'box';
    }
    function stockFromPacks(p, packs) {
        packs = parseFloat(packs) || 0;
        if (!p || !(p.unitsPerPack > 1)) return packs;
        return Math.round(packs * p.unitsPerPack * 100) / 100;
    }
    function otherStock(p, c, except) {
        var used = 0;
        if (except !== 'retail') used += (c.retail || 0);
        if (except !== 'retailPack' && hasRetailPack(p)) used += stockFromPacks(p, c.retailPack || 0);
        if (except !== 'wholesale') {
            used += hasWholesalePack(p) ? stockFromPacks(p, c.wholesale || 0) : (c.wholesale || 0);
        }
        return used;
    }
    function stockUsed(p, c) {
        if (!p || !c) return 0;
        return otherStock(p, c, '');
    }
    function maxRetail(p, c) {
        return Math.max(0, Math.round((p.stock - otherStock(p, c, 'retail')) * 100) / 100);
    }
    function maxRetailPack(p, c) {
        if (!hasRetailPack(p)) return 0;
        var left = Math.max(0, p.stock - otherStock(p, c, 'retailPack'));
        return Math.floor((left / p.unitsPerPack) * 100) / 100;
    }
    function maxWholesale(p, c) {
        var left = Math.max(0, p.stock - otherStock(p, c, 'wholesale'));
        if (hasWholesalePack(p)) return Math.floor((left / p.unitsPerPack) * 100) / 100;
        return Math.round(left * 100) / 100;
    }
    function productPrice(p, type) {
        if (type === 'retail_pack' && hasRetailPack(p)) return p.retailPackPrice;
        if (type === 'wholesale' && hasWholesalePack(p)) return p.packPrice;
        if (type === 'wholesale' && p.wholesale > 0) return p.wholesale;
        return p.price;
    }
    function retailPieces(p, c) {
        return (c.retail || 0) + (hasRetailPack(p) ? stockFromPacks(p, c.retailPack || 0) : 0);
    }
    function retailLineTotal(p, pieces) {
        pieces = parseFloat(pieces) || 0;
        if (pieces <= 0) return 0;
        return pieces * p.price;
    }
    function lineTotal(p, c) {
        return retailLineTotal(p, c.retail || 0)
            + (c.retailPack || 0) * productPrice(p, 'retail_pack')
            + (c.wholesale || 0) * productPrice(p, 'wholesale');
    }
    function serialize(cart, products) {
        var out = [];
        Object.keys(cart).forEach(function (id) {
            var p = products[id], c = cart[id];
            if (!p || !c) return;
            if ((c.retail || 0) > 0) {
                out.push({ product_id: parseInt(id, 10), quantity: c.retail, price_type: 'retail' });
            }
            if ((c.retailPack || 0) > 0 && hasRetailPack(p)) {
                out.push({
                    product_id: parseInt(id, 10),
                    quantity: stockFromPacks(p, c.retailPack),
                    price_type: 'retail_pack',
                    quantity_mode: 'inner'
                });
            }
            if ((c.wholesale || 0) > 0) {
                out.push({
                    product_id: parseInt(id, 10),
                    quantity: hasWholesalePack(p) ? stockFromPacks(p, c.wholesale) : c.wholesale,
                    price_type: 'wholesale',
                    quantity_mode: hasWholesalePack(p) ? 'inner' : 'unit'
                });
            }
        });
        return out;
    }
    function isEmpty(c) {
        return !c || ((c.retail || 0) <= 0 && (c.retailPack || 0) <= 0 && (c.wholesale || 0) <= 0);
    }
    function applyLine(cart, line, product) {
        var id = String(line.product_id);
        if (!cart[id]) cart[id] = buckets();
        var qty = parseFloat(line.quantity) || 0;
        if (line.price_type === 'wholesale') {
            if (line.quantity_mode === 'inner' && product && product.unitsPerPack > 1) {
                qty = Math.round((qty / product.unitsPerPack) * 100) / 100;
            }
            cart[id].wholesale += qty;
        }
        else if (line.price_type === 'retail_pack') {
            if (line.quantity_mode === 'inner' && product && product.unitsPerPack > 1) {
                qty = Math.round((qty / product.unitsPerPack) * 100) / 100;
            }
            cart[id].retailPack += qty;
        }
        else cart[id].retail += qty;
    }
    function clampField(p, c, field, val) {
        val = Math.round((parseFloat(val) || 0) * 100) / 100;
        var max = field === 'retailPack' ? maxRetailPack(p, c)
            : (field === 'wholesale' ? maxWholesale(p, c) : maxRetail(p, c));
        if (val > max) val = max;
        return val > 0 ? val : 0;
    }
    function qtyRow(id, label, priceText, field, value, max) {
        var attr = field === 'retailPack' ? 'retail-pack' : field;
        return '<label class="pos-dual-row"><span class="pos-dual-label">' + label
            + ' <span class="text-muted">(' + priceText + ')</span></span>'
            + '<span class="pos-qty"><button type="button" data-dec-' + attr + '="' + id + '">−</button>'
            + '<button type="button" class="pos-half-btn" data-half-' + attr + '="' + id + '" title="Add half">½</button>'
            + '<input type="number" step="0.5" min="0" max="' + max + '" class="pos-qty-input" data-' + attr + '-qty="' + id + '" value="' + value + '" inputmode="decimal">'
            + '<button type="button" data-inc-' + attr + '="' + id + '">+</button></span></label>';
    }

    global.PosPackCart = {
        buckets: buckets,
        hasRetailPack: hasRetailPack,
        hasWholesalePack: hasWholesalePack,
        packLabel: packLabel,
        stockUsed: stockUsed,
        maxRetail: maxRetail,
        maxRetailPack: maxRetailPack,
        maxWholesale: maxWholesale,
        productPrice: productPrice,
        retailPieces: retailPieces,
        lineTotal: lineTotal,
        serialize: serialize,
        isEmpty: isEmpty,
        applyLine: applyLine,
        clampField: clampField,
        qtyRow: qtyRow
    };
})(window);
