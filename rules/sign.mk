# Signing rules
private_key=$(HOME)/.owncloud/certificates/$(app_name).key
certificate=$(HOME)/.owncloud/certificates/$(app_name).crt
sign=$(OCC) integrity:sign-app --privateKey="$(private_key)" --certificate="$(certificate)"
ifneq (,$(wildcard $(private_key)))
ifneq (,$(wildcard $(certificate)))
ifneq (,$(wildcard $(OCC)))
	CAN_SIGN=true
endif
endif
endif
signature_file=$(build_dir)/$(app_name)/appinfo/signature.json

$(signature_file): $(build_dir)/$(app_name)
	@if test "$(CAN_SIGN)" == "true"; then \
		$(sign) --path "$(build_dir)/$(app_name)"; \
	else \
		echo "Warning: Skipping signing, either no key and certificate found in $(private_key) and $(certificate) or occ can not be found at $(occ)" >&2; \
	fi

.PHONY: sign
sign: $(signature_file)

