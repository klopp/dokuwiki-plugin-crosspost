function cp_link_clicked(a, ns) {
	var cp_to = document.getElementById('crosspost_to');
	if (!cp_to)
		return false;
	var cp_values = cp_to.value.split(/[\s,]/);
	if (cp_values.length == 1 && !cp_values[0])
		cp_values.length = 0;

	var found = false;
	for (var i = 0; i < cp_values.length; i++) {
		if (cp_values[i] == ns) {
			cp_values.splice(i, 1);
			a.className = 'crosspost';
			found = true;
			break;
		}
	}

	if (!found) {
		cp_values[cp_values.length] = ns;
		a.className = 'crosspost_added';
	}
	cp_to.value = cp_values.join(',');
	return false;
}
