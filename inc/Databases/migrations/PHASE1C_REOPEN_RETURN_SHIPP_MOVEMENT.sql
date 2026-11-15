-- Phase 1c: new inventory movement type used when the general/finance manager
-- returns a courier order to the warehouse for correction (reverses RETURN_SHIPP).
-- Appending a value to the ENUM is safe and keeps existing rows. Run on a TEST copy first.
ALTER TABLE `inventory_movement`
  MODIFY `IM_MOVE_TYPE` enum('RECEIVING','SHIPPING','SALES','TRANSFER','SETTLEMENT','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE','REFUNDPURCHISES','REOPEN_RETURN_SHIPP') NOT NULL;
