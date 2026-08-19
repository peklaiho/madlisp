(do
  (def benchmark-tail
    (fn (n)
      (if (= n 0)
          0
          (benchmark-tail (dec n)))))
  (benchmark-tail 10000))
