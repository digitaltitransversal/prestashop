MODULE_NAME := digitalfemsa
BUILD_DIR := /tmp/$(MODULE_NAME)_pkg
ZIP := $(MODULE_NAME).zip
EXCLUDES := --exclude '.git' --exclude '.idea' --exclude '.DS_Store' --exclude '*.zip' --exclude '.php-cs-fixer.php' --exclude '.windsurf' --exclude 'CONTRIBUTING.md'

.PHONY: package clean

package:
	 rm -rf $(BUILD_DIR) && mkdir -p $(BUILD_DIR)/$(MODULE_NAME)
	 rsync -a $(EXCLUDES) ./ $(BUILD_DIR)/$(MODULE_NAME)/
	 cd $(BUILD_DIR) && zip -r $(ZIP) $(MODULE_NAME)
	 mv $(BUILD_DIR)/$(ZIP) .

clean:
	 rm -rf $(BUILD_DIR) $(ZIP)
